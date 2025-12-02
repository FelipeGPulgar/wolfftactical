<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Obtener o crear carrito del usuario
    // Obtener o crear carrito del usuario
    $cartId = null;
    try {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cartId = $stmt->fetchColumn();

        if (!$cartId) {
            $stmt = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            $cartId = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        // Si la tabla carts no existe o falla la columna user_id, retornamos carrito vacío sin error
        // Esto permite que el login funcione aunque el carrito falle
        error_log("Error en carrito (posible falta de tabla/columna): " . $e->getMessage());
        echo json_encode(['success' => true, 'cart' => []]);
        exit;
    }

    if ($method === 'GET') {
        // Obtener items
        $stmt = $pdo->prepare("
            SELECT ci.*, p.name, p.price, pi.path as image 
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_cover = 1
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar opciones JSON
        foreach ($items as &$item) {
            $item['options'] = json_decode($item['options'], true);
        }

        echo json_encode(['success' => true, 'cart' => $items]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'add';

        if ($action === 'sync') {
            // Sincronizar carrito local con DB (Merge)
            $localItems = $input['items'] ?? [];
            foreach ($localItems as $localItem) {
                // Verificar si ya existe este producto con las mismas opciones
                $optionsJson = json_encode($localItem['options'] ?? []);
                
                $check = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND options = ?");
                $check->execute([$cartId, $localItem['id'], $optionsJson]);
                $existing = $check->fetch();

                if ($existing) {
                    // Actualizar cantidad (opcional: sumar o reemplazar. Aquí sumamos si es sync inicial)
                    // Para simplificar, asumiremos que el sync inicial prefiere la suma o mantiene el máximo
                    // Pero lo más común es que si el usuario viene con items, se añadan.
                    $newQty = $existing['quantity'] + $localItem['quantity'];
                    $update = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $update->execute([$newQty, $existing['id']]);
                } else {
                    // Insertar nuevo
                    $insert = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, options) VALUES (?, ?, ?, ?)");
                    $insert->execute([$cartId, $localItem['id'], $localItem['quantity'], $optionsJson]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Carrito sincronizado']);

        } elseif ($action === 'add' || $action === 'update') {
            $productId = $input['product_id'];
            $quantity = $input['quantity'];
            $options = json_encode($input['options'] ?? []);

            // Verificar existencia
            $check = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ? AND options = ?");
            $check->execute([$cartId, $productId, $options]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                if ($quantity > 0) {
                    $update = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $update->execute([$quantity, $existingId]);
                } else {
                    $del = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
                    $del->execute([$existingId]);
                }
            } else {
                if ($quantity > 0) {
                    $insert = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, options) VALUES (?, ?, ?, ?)");
                    $insert->execute([$cartId, $productId, $quantity, $options]);
                }
            }
            echo json_encode(['success' => true]);
        }

    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['item_id'] ?? null;
        
        if ($itemId) {
            // Eliminar item específico (por ID de tabla cart_items, no producto)
            // Pero el frontend suele manejar IDs de producto. Adaptemos.
            // Si el frontend manda product_id y options, usamos eso.
            // Si manda el ID de la fila, usamos eso.
            // Para simplificar, asumiremos que DELETE borra todo el carrito o un item específico.
            
            // Opción A: Borrar todo (Clear cart)
            if ($itemId === 'all') {
                $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
            } else {
                // Borrar item específico por ID de relación
                $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?")->execute([$itemId, $cartId]);
            }
        } else {
             // Borrar por producto y opciones (más complejo de coincidir exacto)
             // Mejor que el frontend maneje la lógica de "update quantity to 0" para borrar items individuales
             // O implementamos un endpoint específico.
             // Aquí asumiremos que DELETE sin ID borra todo el carrito.
             $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
        }
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
