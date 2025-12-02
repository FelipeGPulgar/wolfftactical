<?php
require_once 'cors.php';

// Incluir la conexión a la base de datos
require_once 'db.php';

// Verificar si los datos se enviaron correctamente
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['subcategory_id'])) {
    error_log("Error: Falta el ID de la subcategoría.");
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Falta el ID de la subcategoría.']);
    exit();
}

$subcategory_id = intval($data['subcategory_id']);
error_log("Subcategoría ID recibido: " . $subcategory_id);

try {
    // Verificar si la subcategoría existe (nuevo esquema en tabla subcategories)
    $stmt = $pdo->prepare("SELECT id FROM subcategories WHERE id = :id");
    $stmt->bindParam(':id', $subcategory_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        error_log("Error: Subcategoría no encontrada o no es una subcategoría: ID " . $subcategory_id);
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Subcategoría no encontrada o no válida.']);
        exit();
    }

    // Eliminar la subcategoría
    $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = :id");
    $stmt->bindParam(':id', $subcategory_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        error_log("Subcategoría eliminada correctamente: ID " . $subcategory_id);
        echo json_encode(['success' => true, 'message' => 'Subcategoría eliminada correctamente.']);
    } else {
        error_log("Error: No se pudo eliminar la subcategoría: ID " . $subcategory_id);
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la subcategoría.']);
    }
} catch (PDOException $e) {
    // Si la tabla subcategories no existe, considerarlo como ya eliminado (no-op)
    $msg = $e->getMessage();
    if (stripos($msg, 'subcategories') !== false && (stripos($msg, 'doesn') !== false || stripos($msg, 'no such') !== false || stripos($msg, 'exist') !== false)) {
        error_log('[delete_subcategory] Tabla subcategories no existe. No-op para ID ' . $subcategory_id);
        echo json_encode(['success' => true, 'message' => 'Subcategorías no habilitadas (tabla inexistente). No se requieren acciones.']);
        exit();
    }
    error_log("Error al eliminar la subcategoría: " . $msg);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la subcategoría.', 'error' => $msg]);
}
?>
