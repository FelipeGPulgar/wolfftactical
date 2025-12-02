<?php
// Configuración dinámica de CORS
require_once 'cors.php';

require_once 'db.php';

try {
    $query = "SELECT id, name FROM categories ORDER BY name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($categories);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching categories']);
}
?>