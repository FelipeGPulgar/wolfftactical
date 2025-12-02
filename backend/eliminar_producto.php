<?php
// --- INICIO: Habilitar TODOS los errores para depuración ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- FIN: Depuración ---

require_once __DIR__ . '/secure_session.php';

require_once 'cors.php';

// --- Establecer Content-Type JSON para todas las respuestas reales ---
header("Content-Type: application/json");

// --- Conexión DB (Usando PDO de db.php) y Autenticación ---
require_once 'db.php'; // Asegúrate que la ruta sea correcta y que usa PDO ($pdo)

// Verifica la sesión DESPUÉS de incluir db.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); // No Autorizado
    // Usar die() para asegurar que no continúe
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// --- Lógica de Eliminación ---

// Determinar el método (GET es menos seguro para eliminar, considera DELETE o POST)
$request_method = $_SERVER['REQUEST_METHOD'];
$productId = null;

// Tu frontend actualmente usa GET con el ID en la URL
if ($request_method === 'GET') {
    $productId = $_GET['id'] ?? null;
} else {
    // Si decides cambiar a DELETE o POST en el futuro
    http_response_code(405); // Método no permitido
    die(json_encode(['success' => false, 'message' => 'Método no permitido. Se esperaba GET.']));
}


// Validación del ID
if (!$productId || !filter_var($productId, FILTER_VALIDATE_INT) || (int)$productId <= 0) {
    http_response_code(400); // Solicitud incorrecta
    die(json_encode(['success' => false, 'message' => 'ID de producto inválido o no proporcionado']));
}
$productId = (int)$productId;

try {
    // Obtener datos del producto para la notificación ANTES de borrar
    $productName = null; $productModel = null;
    try {
        $pstmt = $pdo->prepare("SELECT name, model FROM products WHERE id = :id");
        $pstmt->execute([':id' => $productId]);
        $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
        if ($prow) { $productName = $prow['name'] ?? null; $productModel = $prow['model'] ?? null; }
    } catch (Throwable $t) {
        error_log("[Delete Info] No se pudieron obtener datos del producto $productId: " . $t->getMessage());
    }
    // Recopilar todas las imágenes asociadas desde product_images (portada + galería)
    $images = [];
    try {
        $imgStmt = $pdo->prepare("SELECT path FROM product_images WHERE product_id = :id");
        $imgStmt->execute([':id' => $productId]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $t) {
        // Si la tabla no existe o falla, continuar igual
        error_log("[Delete Info] No se pudieron obtener imágenes para el producto $productId: " . $t->getMessage());
        $images = [];
    }

    // Eliminar primero dependencias para evitar restricciones de FK
    try {
        // product_images
        $pdo->prepare("DELETE FROM product_images WHERE product_id = :id")->execute([':id' => $productId]);
    } catch (Throwable $t) {
        error_log("[Delete Info] No se pudieron eliminar imágenes de DB para producto $productId: " . $t->getMessage());
    }
    try {
        // product_colors (si existe)
        $pdo->prepare("DELETE FROM product_colors WHERE product_id = :id")->execute([':id' => $productId]);
    } catch (Throwable $t) {
        // Silencioso si la tabla no existe
    }

    // 2. Preparar y ejecutar la eliminación del producto
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);

    // 3. Verificar si se eliminó alguna fila
    if ($stmt->rowCount() > 0) {
        // Borrar archivos de imágenes del disco después de eliminar en DB
        foreach ($images as $relPath) {
            if (!$relPath) continue;
            $absPath = (strpos($relPath, __DIR__) === 0) ? $relPath : __DIR__ . '/' . ltrim($relPath, '/');
            if (is_file($absPath)) {
                @unlink($absPath);
            }
        }

        // Opcional: Añadir notificación
        try {
            $notificationSql = "INSERT INTO notifications (message, type, created_at) VALUES (:message, :type, NOW())";
            $notificationStmt = $pdo->prepare($notificationSql);
            $msg = "Producto" . ($productName ? " '" . $productName . "'" : "") . " (ID: $productId) eliminado.";
            if ($productModel) { $msg .= " | Modelo: " . $productModel; }
            $notificationStmt->execute([
                'message' => $msg,
                'type' => 'warning' // o 'danger'
            ]);
        } catch (PDOException $e) {
            error_log("Error al guardar notificación de eliminación para producto ID $productId: " . $e->getMessage());
        }

        // Respuesta exitosa JSON
        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);

    } else {
        // Si rowCount es 0, significa que el producto con ese ID no existía
        http_response_code(404); // No encontrado
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado con ID: ' . $productId]);
    }

} catch (PDOException $e) {
    // Captura errores de base de datos (PDO)
    http_response_code(500); // Error Interno del Servidor
    // Loggear el error real es crucial para el desarrollador
    error_log("Error al eliminar producto (ID: $productId): " . $e->getMessage());
    // Mensaje genérico para el cliente
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al eliminar el producto.']);
} catch (Exception $e) {
    // Captura otros errores generales
    http_response_code(500);
    error_log("Error general al eliminar producto (ID: $productId): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
}

exit(); // Terminar el script explícitamente
?>
