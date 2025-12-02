<?php
// Backend endpoint para gestionar colores de productos
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// CORS
require_once 'cors.php';

require_once 'db.php';

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// DELETE: Eliminar un color
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $colorId = $input['color_id'] ?? null;
    
    if (!$colorId || !filter_var($colorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'ID de color inválido']));
    }
    
    try {
        $pdo->beginTransaction();
        
        // Obtener info del color antes de eliminar
        $infoStmt = $pdo->prepare("SELECT pc.color_name, pc.color_hex, p.name as product_name, p.id as product_id 
                                   FROM product_colors pc 
                                   JOIN products p ON pc.product_id = p.id 
                                   WHERE pc.id = :id");
        $infoStmt->execute([':id' => $colorId]);
        $colorInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
        
        // Eliminar color (imágenes se eliminan automáticamente por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM product_colors WHERE id = :id");
        $stmt->execute([':id' => $colorId]);
        
        // Crear notificación
        if ($colorInfo) {
            $notifMsg = "Color '{$colorInfo['color_name']}' ({$colorInfo['color_hex']}) eliminado del producto '{$colorInfo['product_name']}' (ID: {$colorInfo['product_id']})";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (message, type) VALUES (:msg, 'warning')");
            $notifStmt->execute([':msg' => $notifMsg]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Color eliminado']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting color: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar color']);
    }
    exit();
}

// POST no es necesario aquí, se maneja en editar_producto.php
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>
