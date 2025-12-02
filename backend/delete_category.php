<?php
// Configuración dinámica de CORS
require_once 'cors.php';

// Incluir la conexión a la base de datos
session_start();
require_once 'db.php';

// Verificar sesión de admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar si los datos se enviaron correctamente
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['category_id'])) {
    error_log("Error: Falta el ID de la categoría.");
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Falta el ID de la categoría.']);
    exit();
}

$category_id = intval($data['category_id']);
error_log("Categoría ID recibido: " . $category_id);

try {
    // Verificar si la categoría existe y no tiene un parent_id
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        error_log("Error: Categoría no encontrada o no es una categoría principal: ID " . $category_id);
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada o no válida.']);
        exit();
    }

    // Verificar si hay productos usando esta categoría
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
    $countStmt->execute([':id' => $category_id]);
    $inUse = (int)$countStmt->fetchColumn();
    $reassigned = 0;
    if ($inUse > 0) {
        // Buscar o crear categoría de fallback "FALTA CATEGORIA"
        $fallbackName = 'FALTA CATEGORIA';
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
            $check->execute([':name' => $fallbackName]);
            $fallbackId = $check->fetchColumn();
            if (!$fallbackId) {
                // Generar slug para cumplir con esquema
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', @iconv('UTF-8','ASCII//TRANSLIT',$fallbackName)),'-'));
                if (!$slug) { $slug = 'falta-categoria'; }
                $ins = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
                $ins->execute([':name' => $fallbackName, ':slug' => $slug]);
                $fallbackId = $pdo->lastInsertId();
            }

            // Reasignar productos a la categoría fallback
            $upd = $pdo->prepare('UPDATE products SET category_id = :fallback WHERE category_id = :old');
            $upd->execute([':fallback' => $fallbackId, ':old' => $category_id]);
            $reassigned = $upd->rowCount();
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[delete_category] Error al crear/reasignar a fallback: ' . $t->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo reasignar productos a la categoría de fallback.']);
            exit();
        }
    }

    // Eliminar las subcategorías asociadas (nuevo esquema en tabla subcategories)
    try {
        $stmtSub = $pdo->prepare("DELETE FROM subcategories WHERE category_id = :category_id");
        $stmtSub->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmtSub->execute();
        error_log("Subcategorías eliminadas para la categoría ID: " . $category_id);
    } catch (Throwable $t) {
        error_log('[delete_category] No se eliminaron subcategorías (tabla puede no existir): ' . $t->getMessage());
    }

    // Eliminar la categoría principal
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        error_log("Categoría eliminada correctamente: ID " . $category_id);
        // Notificación opcional
        try {
            $notificationSql = "INSERT INTO notifications (message, type, created_at) VALUES (:message, :type, NOW())";
            $notificationStmt = $pdo->prepare($notificationSql);
            $notifMsg = 'Categoría ID ' . $category_id . ' eliminada. Productos reasignados: ' . $reassigned;
            $notificationStmt->execute([':message' => $notifMsg, ':type' => 'warning']);
        } catch (Throwable $t) { /* noop */ }

        echo json_encode(['success' => true, 'message' => 'Categoría eliminada. Productos reasignados: ' . $reassigned]);
    } else {
        error_log("Error: No se pudo eliminar la categoría: ID " . $category_id);
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la categoría.']);
    }
} catch (PDOException $e) {
    error_log("Error al eliminar la categoría: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la categoría.', 'error' => $e->getMessage()]);
}
?>
