<?php
// --- INICIO: Habilitar errores para depuración ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// --- FIN: Depuración ---

// session_start(); // Removed to prevent session locking
$debug = isset($_GET['debug']);

require_once __DIR__ . '/cors.php';

require_once 'db.php';

try {
    // --- Parámetros de entrada ---
    $productId = isset($_GET['id']) ? $_GET['id'] : null;
    $categoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $show = isset($_GET['show']) ? $_GET['show'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $brandId = isset($_GET['brand_id']) ? $_GET['brand_id'] : null; // Can be single ID or comma-separated

    // --- Validación de Parámetros ---
    if ($productId !== null && !filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'ID de producto inválido.']));
    }
    if ($categoryId !== null && $categoryId !== '' && !filter_var($categoryId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'ID de categoría inválido.']));
    }

    // --- Construcción de la Consulta SQL ---
    // Usar consulta simple como en el referente
    $sql = "SELECT p.* FROM products p";

    $conditions = [];
    $params = [];

    // Condición de activo (por defecto)
    if ($show !== 'all') {
        $conditions[] = "p.is_active = 1";
    }

    // Filtro por ID de producto
    if ($productId) {
        $conditions[] = "p.id = :product_id";
        $params[':product_id'] = $productId;
    }
    elseif ($categoryId) {
        $conditions[] = "p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    // Filtro de búsqueda
    if ($search) {
        $conditions[] = "(p.name LIKE :search OR p.description LIKE :search OR p.model LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Filtros Avanzados
    if ($minPrice !== null) {
        $conditions[] = "p.price >= :min_price";
        $params[':min_price'] = $minPrice;
    }
    if ($maxPrice !== null) {
        $conditions[] = "p.price <= :max_price";
        $params[':max_price'] = $maxPrice;
    }
    if ($brandId) {
        // Soporte para múltiples marcas (comma-separated)
        if (strpos($brandId, ',') !== false) {
            $brandIds = explode(',', $brandId);
            $brandPlaceholders = [];
            foreach ($brandIds as $index => $bid) {
                $ph = ":brand_id_" . $index;
                $brandPlaceholders[] = $ph;
                $params[$ph] = (int)$bid;
            }
            $conditions[] = "p.brand_id IN (" . implode(',', $brandPlaceholders) . ")";
        } else {
            $conditions[] = "p.brand_id = :brand_id";
            $params[':brand_id'] = (int)$brandId;
        }
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    // --- Ordenación ---
    $orderBy = "";
    switch ($sort) {
        case 'price_asc':
            $orderBy = " ORDER BY p.price ASC, p.id ASC";
            break;
        case 'price_desc':
            $orderBy = " ORDER BY p.price DESC, p.id DESC";
            break;
        case 'name':
            $orderBy = " ORDER BY p.name ASC, p.id ASC";
            break;
        case 'newest':
        default:
            $orderBy = " ORDER BY p.id DESC"; // Usar id DESC para los más nuevos
            break;
    }
    $sql .= $orderBy;

    // --- Ejecutar Consulta ---
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // --- Procesar Resultados ---
    if ($productId) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado o inactivo.']);
        } else {
            // Agregar cover_image (Lógica del referente)
            try {
                $coverStmt = $pdo->prepare("SELECT path FROM product_images WHERE product_id = :pid AND is_cover = 1 LIMIT 1");
                $coverStmt->execute([':pid' => $productId]);
                $cover = $coverStmt->fetch(PDO::FETCH_ASSOC);
                $product['cover_image'] = $cover ? $cover['path'] : null;
            } catch (PDOException $e) {
                $product['cover_image'] = null;
            }

            // Obtener nombre de categoría
            $product['category_name'] = null;
            if ($product['category_id']) {
                try {
                    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = :cat_id LIMIT 1");
                    $catStmt->execute([':cat_id' => $product['category_id']]);
                    $category = $catStmt->fetch(PDO::FETCH_ASSOC);
                    $product['category_name'] = $category ? $category['name'] : null;
                } catch (PDOException $e) {
                    // Silenciar error
                }
            }

            // Obtener imágenes y colores adicionales para la vista de detalle
            try {
                $imgsStmt = $pdo->prepare("SELECT id, path, is_cover, sort_order FROM product_images WHERE product_id = :pid ORDER BY is_cover DESC, sort_order ASC, id ASC");
                $imgsStmt->execute([':pid' => $productId]);
                $images = $imgsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                $images = [];
            }

            try {
                $colorsStmt = $pdo->prepare("
                    SELECT 
                        pc.id, 
                        pc.color_name, 
                        pc.color_hex,
                        pci.path as image_path
                    FROM product_colors pc
                    LEFT JOIN product_color_images pci ON pc.id = pci.product_color_id AND pci.sort_order = 0
                    WHERE pc.product_id = :pid 
                    ORDER BY pc.id ASC
                ");
                $colorsStmt->execute([':pid' => $productId]);
                $colors = $colorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                $colors = [];
            }

            echo json_encode(['success' => true, 'data' => $product, 'images' => $images, 'colors' => $colors]);
        }
    } else {
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar cover_image y category_name a cada producto (Lógica del referente)
        foreach ($products as &$product) {
            // Agregar cover_image
            try {
                $coverStmt = $pdo->prepare("SELECT path FROM product_images WHERE product_id = :pid AND is_cover = 1 LIMIT 1");
                $coverStmt->execute([':pid' => $product['id']]);
                $cover = $coverStmt->fetch(PDO::FETCH_ASSOC);
                $product['cover_image'] = $cover ? $cover['path'] : null;
            } catch (PDOException $e) {
                $product['cover_image'] = null;
            }
            
            // Agregar category_name
            $product['category_name'] = null;
            if ($product['category_id']) {
                try {
                    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = :cat_id LIMIT 1");
                    $catStmt->execute([':cat_id' => $product['category_id']]);
                    $category = $catStmt->fetch(PDO::FETCH_ASSOC);
                    $product['category_name'] = $category ? $category['name'] : null;
                } catch (PDOException $e) {
                    // Silenciar error
                }
            }
        }
        unset($product); // Liberar referencia
        
        echo json_encode(['success' => true, 'data' => $products]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database Error in get_products.php: ' . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A') . " | Params: " . json_encode($params ?? []));
    $resp = ['success' => false, 'message' => 'Error en la base de datos al obtener productos.'];
    if ($debug) { $resp['debug'] = ['error' => $e->getMessage(), 'sql' => ($sql ?? null), 'params' => ($params ?? null)]; }
    echo json_encode($resp);
} catch (Exception $e) {
    http_response_code(500);
    error_log('General Error in get_products.php: ' . $e->getMessage());
    $resp = ['success' => false, 'message' => 'Ocurrió un error inesperado.'];
    if ($debug) { $resp['debug'] = ['error' => $e->getMessage(), 'sql' => ($sql ?? null), 'params' => ($params ?? null)]; }
    echo json_encode($resp);
}
exit();
?>