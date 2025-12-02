<?php
require_once 'db.php';

header('Content-Type: application/json');

// Helper function to send JSON response
function sendResponse($success, $message = '', $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_category':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) sendResponse(false, 'El nombre es obligatorio');
            
            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
            $stmt->execute([':name' => $name, ':slug' => $slug]);
            sendResponse(true, 'Categoría creada', ['id' => $pdo->lastInsertId(), 'name' => $name]);
            break;

        case 'add_brand':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) sendResponse(false, 'El nombre es obligatorio');
            
            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug) VALUES (:name, :slug)");
            $stmt->execute([':name' => $name, ':slug' => $slug]);
            sendResponse(true, 'Marca creada', ['id' => $pdo->lastInsertId(), 'name' => $name]);
            break;

        case 'check_usage':
            $type = $_POST['type'] ?? ''; // 'category' or 'brand'
            $id = $_POST['id'] ?? 0;
            
            if ($type === 'category') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
            } elseif ($type === 'brand') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = :id");
            } else {
                sendResponse(false, 'Tipo inválido');
            }
            
            $stmt->execute([':id' => $id]);
            $count = $stmt->fetchColumn();
            sendResponse(true, '', ['count' => $count]);
            break;

        case 'delete_category':
            $id = $_POST['id'] ?? 0;
            // Set products to NULL (requires DB change to allow NULL)
            $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $id]);
            sendResponse(true, 'Categoría eliminada');
            break;

        case 'delete_brand':
            $id = $_POST['id'] ?? 0;
            $pdo->prepare("UPDATE products SET brand_id = NULL WHERE brand_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM brands WHERE id = :id")->execute([':id' => $id]);
            sendResponse(true, 'Marca eliminada');
            break;

        default:
            sendResponse(false, 'Acción no válida');
    }
} catch (PDOException $e) {
    sendResponse(false, 'Error de base de datos: ' . $e->getMessage());
}
