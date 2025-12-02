<?php
require_once 'db.php';

header('Content-Type: application/json');

require_once 'cors.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($brands);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener marcas: ' . $e->getMessage()]);
}
?>
