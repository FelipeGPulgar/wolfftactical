<?php
require_once 'db.php';

header('Content-Type: application/json');
require_once 'cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name']) || empty(trim($data['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre de la marca es obligatorio']);
    exit;
}

$name = trim($data['name']);

// Función simple para slug (puedes mejorarla o usar una librería)
function createSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

$slug = createSlug($name);

try {
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'La marca ya existe']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO brands (name, slug) VALUES (?, ?)");
    $stmt->execute([$name, $slug]);
    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Marca creada exitosamente',
        'brand' => [
            'id' => $id,
            'name' => $name,
            'slug' => $slug
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
