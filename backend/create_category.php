<?php
// Configuración dinámica de CORS
require_once 'cors.php';

// Incluir la conexión a la base de datos
require_once 'db.php';

// Verificar si los datos se enviaron correctamente
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['name'])) {
    error_log("Error: Falta el nombre de la categoría.");
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Falta el nombre de la categoría.']);
    exit();
}

$name = trim($data['name']);
error_log("Nombre de la categoría recibido: " . $name);

try {
    // Generar slug sencillo a partir del nombre
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)),'-'));

    // Insertar categoría (nuevo esquema: name, slug)
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();

    $category_id = $pdo->lastInsertId();
    error_log("Categoría creada correctamente: ID " . $category_id);

    echo json_encode(['success' => true, 'message' => 'Categoría creada correctamente.', 'category' => ['id' => $category_id, 'name' => $name, 'slug' => $slug]]);
} catch (PDOException $e) {
    $code = $e->getCode();
    $msg = $e->getMessage();
    if ($code == 23000) { // Duplicate (name o slug)
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'La categoría ya existe.']);
        exit();
    }
    error_log("Error al crear la categoría: " . $msg);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al crear la categoría.', 'error' => $msg]);
}
?>
