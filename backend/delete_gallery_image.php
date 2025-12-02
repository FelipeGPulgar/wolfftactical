<?php
// Backend endpoint para eliminar imágenes de galería
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'secure_session.php';

// CORS
require_once 'cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// DELETE: Eliminar una imagen
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $imageId = $input['image_id'] ?? null;
    
    if (!$imageId || !filter_var($imageId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'ID de imagen inválido']));
    }
    
    try {
        $pdo->beginTransaction();
        
        // Obtener info de la imagen antes de eliminar
        $infoStmt = $pdo->prepare("SELECT pi.path, p.name as product_name, p.id as product_id 
                                   FROM product_images pi 
                                   JOIN products p ON pi.product_id = p.id 
                                   WHERE pi.id = :id");
        $infoStmt->execute([':id' => $imageId]);
        $imageInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$imageInfo) {
            $pdo->rollBack();
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'Imagen no encontrada']));
        }
        
        // Eliminar imagen de la base de datos
        $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = :id");
        $stmt->execute([':id' => $imageId]);
        
        // Intentar eliminar archivo físico (opcional, no crítico)
        $filePath = __DIR__ . '/' . $imageInfo['path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        // Crear notificación
        $notifMsg = "Imagen eliminada del producto '{$imageInfo['product_name']}' (ID: {$imageInfo['product_id']})";
        $notifStmt = $pdo->prepare("INSERT INTO notifications (message, type) VALUES (:msg, 'warning')");
        $notifStmt->execute([':msg' => $notifMsg]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting image: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar imagen']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>
