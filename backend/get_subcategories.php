<?php
// Configuración dinámica de CORS
require_once 'cors.php';

require_once 'db.php';

if (!isset($_GET['category_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'category_id is required']);
    exit;
}

$category_id = intval($_GET['category_id']);

try {
    try {
        $query = "SELECT id, name FROM subcategories WHERE category_id = :category_id ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        // Si no existe la tabla subcategories, devolver lista vacía
        error_log('[get_subcategories] Tabla subcategories no disponible: ' . $t->getMessage());
        $subcategories = [];
    }

    echo json_encode($subcategories);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching subcategories']);
}
?>