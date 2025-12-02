<?php
require_once 'cors.php';

// Incluir la conexión a la base de datos
require_once 'db.php';

// Verificar si los datos se enviaron correctamente
$data = json_decode(file_get_contents("php://input"), true);
error_log("Datos recibidos en create_subcategory.php: " . json_encode($data));

if (!isset($data['name']) || !isset($data['parent_id'])) { // Validar parent_id (desde frontend)
    error_log("Error: Faltan datos para crear la subcategoría.");
    error_log("Datos recibidos: " . json_encode($data)); // Depuración adicional
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Faltan datos para crear la subcategoría.']);
    exit();
}

$name = trim($data['name']);
$parent_id = intval($data['parent_id']); // Corresponde a category_id en nuevo esquema
error_log("Nombre de la subcategoría recibido: " . $name . ", Parent ID (category_id): " . $parent_id);

try {
    // Verificar si la categoría principal existe (nuevo esquema no tiene parent_id)
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $parent_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        error_log("Error: Categoría principal no encontrada o no válida: ID " . $parent_id);
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Categoría principal no encontrada.']);
        exit();
    }

    // Generar slug para la subcategoría
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)),'-'));

    // Insertar en tabla subcategories
    $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, name, slug) VALUES (:category_id, :name, :slug)");
    $stmt->bindParam(':category_id', $parent_id, PDO::PARAM_INT);
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();

    $subcategory_id = $pdo->lastInsertId();
    error_log("Subcategoría creada correctamente: ID " . $subcategory_id);

    echo json_encode(['success' => true, 'message' => 'Subcategoría creada correctamente.', 'subcategory' => ['id' => $subcategory_id, 'name' => $name, 'slug' => $slug]]);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = $e->getCode();
    if (stripos($msg, 'subcategories') !== false && (stripos($msg, 'doesn') !== false || stripos($msg, 'no such') !== false || stripos($msg, 'exist') !== false)) {
        // Si la tabla no existe tratamos subcategorías como desactivadas
        error_log('[create_subcategory] Tabla subcategories no existe. Devolver no-op.');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Subcategorías no habilitadas (tabla inexistente).']);
        exit();
    }
    if ($code == 23000) { // Duplicado
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'La subcategoría ya existe.']);
        exit();
    }
    error_log("Error al crear la subcategoría: " . $msg);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al crear la subcategoría.', 'error' => $msg]);
}
?>
