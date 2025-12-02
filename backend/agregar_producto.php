<?php
require_once __DIR__ . '/secure_session.php'; // Ensure secure session is loaded
require_once __DIR__ . '/admin_auth.php';
require_admin();

require_once 'db.php';
require_once 'image_utils.php';

// --- Handle POST Request (Add Product) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json"); // Response will be JSON
    
    // --- Helper Functions from backend/agregar_producto.php ---
    // (We can assume image_utils.php handles image processing)

    try {
        // ... (Logic from backend/agregar_producto.php adapted) ...
        // Validate inputs
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $model = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_SPECIAL_CHARS);
        $category_id = filter_input(INPUT_POST, 'main_category', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $brand_id = filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $stock_option = $_POST['stock_option'] ?? 'preorder';
        $stock_quantity_input = $_POST['stock_quantity'] ?? null;
        $price_input = $_POST['price'] ?? null;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        $descripcion = $_POST['descripcion'] ?? null;
        $incluye = $_POST['incluye'] ?? null;
        $video_url = $_POST['video_url'] ?? null;
        $delivery_days = $_POST['delivery_days'] ?? null;

        if (empty($name) || $price_input === null || $category_id === false || $category_id === null) {
            throw new Exception('Nombre, precio y categoría principal válida son requeridos.');
        }
        if (!is_numeric($price_input) || (float)$price_input < 0) {
            throw new Exception('El precio debe ser un número válido (mayor o igual a 0).');
        }
        $price = (float)$price_input;

        $stock_quantity = null;
        if ($stock_option === 'instock') {
            $stock_quantity = (is_numeric($stock_quantity_input) && (int)$stock_quantity_input >= 0) ? (int)$stock_quantity_input : null;
        }

        // --- Handle Main Image ---
        $main_image_relative_path = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['main_image']['tmp_name'])) {
            $main_image = $_FILES['main_image'];
            $target_dir_relative = "uploads/products/main/";
            $target_dir_absolute = __DIR__ . '/' . $target_dir_relative;
            
            if (!is_dir($target_dir_absolute)) mkdir($target_dir_absolute, 0755, true);
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $imageFileType = strtolower(pathinfo($main_image['name'], PATHINFO_EXTENSION));
            if (!in_array($imageFileType, $allowed_extensions)) throw new Exception('Tipo de imagen no permitido.');

            $new_filename_base = uniqid('prod_');
            $processed = processUploadedImage($main_image, $target_dir_absolute, $new_filename_base);
            if ($processed === false) throw new Exception('Error al procesar imagen principal.');
            $main_image_relative_path = $target_dir_relative . $processed['path'];
        } else {
            throw new Exception('La imagen principal es requerida.');
        }

        $pdo->beginTransaction();

        // Generate Slug
        $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', @iconv('UTF-8','ASCII//TRANSLIT',$name)),'-'));
        if (!$slugBase) $slugBase = strtolower(trim(preg_replace('/\s+/', '-', $name), '-'));
        $slug = $slugBase;
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug');
        $suffix = 2;
        while (true) {
            $checkStmt->execute([':slug' => $slug]);
            if ($checkStmt->fetchColumn() == 0) break;
            $slug = $slugBase . '-' . $suffix++;
        }

        $stock_status = ($stock_option === 'instock') ? 'en_stock' : 'por_encargo';

        // Generate SKU if empty
        if (empty($model)) {
            $prefix = 'SKU';
            $cleanName = preg_replace('/[^a-zA-Z0-9]/', '', $name);
            if (strlen($cleanName) >= 3) $prefix = strtoupper(substr($cleanName, 0, 3));
            $model = $prefix . '-' . mt_rand(10000, 99999);
        }

        // Insert Product
        $sqlInsertProduct = "INSERT INTO products (name, slug, description, price, model, category_id, brand_id, stock_status, stock_quantity, includes_note, video_url, is_active, delivery_days) VALUES (:name, :slug, :description, :price, :model, :category_id, :brand_id, :stock_status, :stock_quantity, :includes_note, :video_url, :is_active, :delivery_days)";
        $stmtProduct = $pdo->prepare($sqlInsertProduct);
        $stmtProduct->execute([
            ':name' => $name, ':slug' => $slug, ':description' => $descripcion ?: null, ':price' => $price, ':model' => $model, ':category_id' => $category_id, ':brand_id' => $brand_id ?: null, ':stock_status' => $stock_status, ':stock_quantity' => $stock_quantity, ':includes_note' => $incluye ?: null, ':video_url' => $video_url ?: null, ':is_active' => $is_active, ':delivery_days' => ($stock_status === 'por_encargo') ? ($delivery_days ?: null) : null
        ]);
        $newProductId = $pdo->lastInsertId();

        // Insert Main Image
        if ($main_image_relative_path) {
            $pdo->prepare("INSERT INTO product_images (product_id, path, alt, is_cover, sort_order) VALUES (:pid, :path, :alt, 1, 1)")
                ->execute([':pid' => $newProductId, ':path' => $main_image_relative_path, ':alt' => $name]);
        }

        // Handle Additional Images
        $additional_images_files = [];
        if (isset($_FILES['additional_images'])) {
            $multi = $_FILES['additional_images'];
            if (is_array($multi['name'])) {
                for ($i = 0; $i < count($multi['name']); $i++) {
                    if (!empty($multi['name'][$i])) {
                        $additional_images_files[] = [
                            'name' => $multi['name'][$i], 'type' => $multi['type'][$i], 'tmp_name' => $multi['tmp_name'][$i], 'error' => $multi['error'][$i], 'size' => $multi['size'][$i]
                        ];
                    }
                }
            }
        }

        $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, path, alt, is_cover, sort_order) VALUES (:product_id, :path, :alt, 0, :sort_order)");
        $currentSortOrder = 2; // Start from 2 since main image is 1
        foreach ($additional_images_files as $index => $imageFile) {
            if ($imageFile['error'] === UPLOAD_ERR_OK) {
                $gallery_dir_relative = "uploads/products/gallery/";
                $gallery_dir_absolute = __DIR__ . '/' . $gallery_dir_relative;
                if (!is_dir($gallery_dir_absolute)) mkdir($gallery_dir_absolute, 0755, true);
                
                $processed = processUploadedImage($imageFile, $gallery_dir_absolute, uniqid('prod_' . $newProductId . '_add' . ($index + 1) . '_'));
                if ($processed !== false) {
                    $stmtImg->execute([':product_id' => $newProductId, ':path' => $gallery_dir_relative . $processed['path'], ':alt' => $name, ':sort_order' => $currentSortOrder]);
                    $currentSortOrder++;
                }
            }
        }

        // Handle Colors
        if (isset($_POST['colors']) && is_array($_POST['colors'])) {
            $stmtColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, color_hex) VALUES (:product_id, :color_name, :color_hex)");
            $stmtColorImage = $pdo->prepare("INSERT INTO product_color_images (product_color_id, path, alt, sort_order) VALUES (:color_id, :path, :alt, 0)");
            
            foreach ($_POST['colors'] as $index => $colorData) {
                if (!empty($colorData['hex'])) {
                    $colorName = !empty($colorData['name']) ? $colorData['name'] : $colorData['hex'];
                    $stmtColor->execute([':product_id' => $newProductId, ':color_name' => $colorName, ':color_hex' => $colorData['hex']]);
                    $colorId = $pdo->lastInsertId();
                    
                    $imageKey = "color_image_{$index}";
                    if (isset($_FILES[$imageKey]) && $_FILES[$imageKey]['error'] === UPLOAD_ERR_OK) {
                        $colors_dir_relative = "uploads/products/colors/";
                        $colors_dir_absolute = __DIR__ . '/' . $colors_dir_relative;
                        if (!is_dir($colors_dir_absolute)) mkdir($colors_dir_absolute, 0755, true);
                        
                        $processedColor = processUploadedImage($_FILES[$imageKey], $colors_dir_absolute, uniqid('color_' . $newProductId . '_' . $index . '_'));
                        if ($processedColor !== false) {
                            $stmtColorImage->execute([':color_id' => $colorId, ':path' => $colors_dir_relative . $processedColor['path'], ':alt' => $name . ' - ' . $colorName]);
                        }
                    }
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Producto agregado con éxito', 'id' => $newProductId]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Return 200 so JS can read the error message
        http_response_code(200); 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Fetch brands
$brands = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM brands ORDER BY name");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Brands table might not exist or be empty
}

$activePage = 'agregar';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wolff Tactical - Agregar Producto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        slate: {
                            850: '#151e2e',
                            900: '#0f172a',
                        },
                        blue: {
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
        .input-tactical { background-color: #1e293b; border: 1px solid #334155; color: white; transition: all 0.2s ease-in-out; }
        .input-tactical:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); outline: none; }
        .drop-zone { transition: all 0.3s ease; background-image: url("data:image/svg+xml,%3csvg width='100%25' height='100%25' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100%25' height='100%25' fill='none' rx='12' ry='12' stroke='%23475569FF' stroke-width='2' stroke-dasharray='12%2c 12' stroke-dashoffset='0' stroke-linecap='square'/%3e%3c/svg%3e"); }
        .drop-zone:hover, .drop-zone.dragover { background-color: rgba(30, 41, 59, 0.8); background-image: url("data:image/svg+xml,%3csvg width='100%25' height='100%25' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100%25' height='100%25' fill='none' rx='12' ry='12' stroke='%233B82F6FF' stroke-width='2' stroke-dasharray='12%2c 12' stroke-dashoffset='0' stroke-linecap='square'/%3e%3c/svg%3e"); transform: translateY(-2px); }
        
        /* Quill Dark Theme Overrides */
        .ql-toolbar.ql-snow { border-color: #334155; background: #1e293b; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; }
        .ql-container.ql-snow { border-color: #334155; background: #1e293b; border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; color: #e2e8f0; font-family: 'Inter', sans-serif; font-size: 1rem; }
        .ql-stroke { stroke: #94a3b8 !important; }
        .ql-fill { fill: #94a3b8 !important; }
        .ql-picker { color: #94a3b8 !important; }
        .ql-picker-options { background-color: #1e293b !important; border-color: #334155 !important; }
        .ql-editor.ql-blank::before { color: #64748b; font-style: normal; }
    </style>
</head>
<body class="antialiased min-h-screen flex">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 overflow-y-auto">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                    <span>Wolfftactical</span> <i class="fa-solid fa-chevron-right text-xs"></i> <span class="text-blue-400">Agregar Producto</span>
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight">Agregar Nuevo Producto</h1>
            </div>
            <div class="flex gap-3 w-full md:w-auto">
                <button type="button" onclick="window.location.href='productos.php'" class="flex-1 md:flex-none px-5 py-2.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800 hover:text-white transition font-medium text-sm">
                    Cancelar
                </button>
                <button onclick="saveProduct()" type="button" class="flex-1 md:flex-none px-6 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-500 shadow-lg shadow-blue-900/40 transition flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-save"></i> Publicar Producto
                </button>
            </div>
        </header>

        <form id="productForm" class="max-w-5xl mx-auto space-y-8">
            <!-- General Info -->
            <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-blue-500 text-sm"></i> Información Básica
                    </h2>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Nombre del Producto <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" class="w-full rounded-lg px-4 py-3 input-tactical" placeholder="Ej: Chaleco Táctico Porta Placas V2">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Modelo / SKU <span class="text-xs text-slate-500 font-normal ml-1">(Opcional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3.5 text-slate-500 text-sm"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" id="model" name="model" class="w-full rounded-lg pl-9 pr-4 py-3 input-tactical" placeholder="Dejar vacío para autogenerar">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Video Review (URL)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3.5 text-slate-500 text-sm"><i class="fa-brands fa-youtube"></i></span>
                                <input type="url" id="video_url" name="video_url" class="w-full rounded-lg pl-9 pr-4 py-3 input-tactical" placeholder="https://youtube.com/...">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Descripción Técnica</label>
                        <div class="h-64">
                            <div id="editor-descripcion"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media -->
            <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fa-solid fa-images text-purple-500 text-sm"></i> Galería Multimedia
                    </h2>
                    <span class="text-xs bg-slate-700 text-slate-300 px-2 py-1 rounded">Máximo 21 imágenes (1 portada + 20 galería)</span>
                </div>
                <div class="p-6">
                    <div id="dropZone" class="drop-zone rounded-xl p-8 md:p-12 text-center cursor-pointer relative group bg-slate-900/50">
                        <input type="file" id="fileInput" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="handleFiles(this.files)">
                        <div class="pointer-events-none transition-transform group-hover:scale-105 duration-300">
                            <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-700 group-hover:border-blue-500 group-hover:text-blue-500 transition-colors">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 group-hover:text-blue-500"></i>
                            </div>
                            <h3 class="text-lg font-medium text-white mb-1">Arrastra y suelta tus imágenes</h3>
                            <p class="text-slate-500 text-sm">Soporta JPG, PNG, WEBP</p>
                        </div>
                    </div>
                    <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mt-6 empty:hidden"></div>
                </div>
            </div>

            <!-- Colors -->
            <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fa-solid fa-palette text-green-500 text-sm"></i> Variantes de Color
                    </h2>
                    <button type="button" onclick="addColor()" class="text-xs bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded transition flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Añadir
                    </button>
                </div>
                <div class="p-6">
                    <div id="colorsContainer" class="space-y-3">
                        <!-- Default Color Row -->

                    </div>
                </div>
            </div>
            <!-- Price & Organization Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pricing -->
                <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden h-full">
                    <div class="p-5 border-b border-slate-700/50 bg-slate-800/30 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Precio e Inventario</h3>
                        
                        <!-- Active Toggle -->
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" checked class="sr-only peer">
                            <div class="relative w-11 h-6 bg-slate-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ms-3 text-xs font-medium text-slate-300">Visible</span>
                        </label>
                    </div>
                    <div class="p-6 space-y-6">
                        <div>
                            <label class="block text-xs text-slate-400 font-bold mb-2 uppercase">Precio de Venta (CLP)</label>
                            <div class="relative group">
                                <span class="absolute left-4 top-3 text-emerald-500 font-bold text-lg">$</span>
                                <input type="text" id="price" name="price" class="w-full rounded-lg pl-8 pr-4 py-3 bg-slate-900 border border-slate-600 text-emerald-400 font-bold text-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition" placeholder="0" oninput="formatPrice(this)">
                                <span class="absolute right-4 top-4 text-xs text-slate-500">CLP</span>
                            </div>
                        </div>
                        <hr class="border-slate-700/50">
                        <div class="space-y-4">
                            <label class="block text-xs text-slate-400 font-bold mb-1 uppercase">Disponibilidad</label>
                            
                            <!-- Stock Options -->
                            <div class="grid grid-cols-1 gap-3">
                                <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-700/50 bg-slate-800/30 cursor-pointer hover:border-blue-500/50 transition relative overflow-hidden">
                                    <input type="radio" name="stock_option" value="instock" checked class="mt-1 text-blue-600 focus:ring-blue-500 bg-slate-700 border-slate-600" onchange="toggleStockInput()">
                                    <div>
                                        <span class="block text-sm font-medium text-white">En Stock</span>
                                        <span class="block text-xs text-slate-500">Envío inmediato desde bodega.</span>
                                    </div>
                                </label>
                                
                                <!-- Quantity Input (Hidden by default if not instock, but here checked is instock) -->
                                <div id="stockQuantityContainer" class="ml-8 transition-all duration-300 overflow-hidden" style="max-height: 100px; opacity: 1;">
                                    <label class="block text-xs text-slate-500 mb-1">Cantidad Disponible</label>
                                    <input type="number" name="stock_quantity" class="w-full rounded-lg px-3 py-2 bg-slate-900 border border-slate-600 text-white text-sm focus:border-blue-500 outline-none" placeholder="Ej: 50" min="0">
                                </div>

                                <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-700/50 bg-slate-800/30 cursor-pointer hover:border-yellow-500/50 transition relative overflow-hidden">
                                    <input type="radio" name="stock_option" value="preorder" class="mt-1 text-yellow-600 focus:ring-yellow-500 bg-slate-700 border-slate-600" onchange="toggleStockInput()">
                                    <div>
                                        <span class="block text-sm font-medium text-white">Por Encargo</span>
                                        <span class="block text-xs text-slate-500">Sujeto a importación.</span>
                                    </div>
                                </label>

                                <!-- Delivery Days Input (Hidden by default) -->
                                <div id="deliveryDaysContainer" class="ml-8 transition-all duration-300 overflow-hidden" style="max-height: 0; opacity: 0; margin-top: -10px;">
                                    <label class="block text-xs text-slate-500 mb-1">Días de Entrega (Aprox.)</label>
                                    <input type="text" name="delivery_days" class="w-full rounded-lg px-3 py-2 bg-slate-900 border border-slate-600 text-white text-sm focus:border-yellow-500 outline-none" placeholder="Ej: 15-20" disabled>
                                    <p class="text-[10px] text-slate-500 mt-1">Si se deja vacío, el cliente deberá consultar.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Organization -->
                <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden h-full">
                    <div class="p-5 border-b border-slate-700/50 bg-slate-800/30">
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Organización</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-xs text-slate-400 font-bold mb-2 uppercase">Categoría Principal</label>
                            <div class="flex gap-2">
                                <select id="main_category" name="main_category" class="w-full rounded-lg px-4 py-2.5 input-tactical appearance-none cursor-pointer">
                                    <option value="" disabled selected>Seleccionar...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openAddModal('category')" class="px-3 py-2 bg-slate-700 hover:bg-blue-600 text-white rounded-lg transition"><i class="fa-solid fa-plus"></i></button>
                                <button type="button" onclick="confirmDelete('category')" class="px-3 py-2 bg-slate-700 hover:bg-red-600 text-white rounded-lg transition"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 font-bold mb-2 uppercase">Marca</label>
                            <div class="flex gap-2">
                                <select id="brand_id" name="brand_id" class="w-full rounded-lg px-4 py-2.5 input-tactical appearance-none cursor-pointer">
                                    <option value="" disabled selected>Seleccionar...</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openAddModal('brand')" class="px-3 py-2 bg-slate-700 hover:bg-blue-600 text-white rounded-lg transition"><i class="fa-solid fa-plus"></i></button>
                                <button type="button" onclick="confirmDelete('brand')" class="px-3 py-2 bg-slate-700 hover:bg-red-600 text-white rounded-lg transition"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What Includes -->
            <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden">
                <div class="p-5 border-b border-slate-700/50 bg-slate-800/30">
                    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">¿Qué incluye la caja?</h3>
                </div>
                <div class="p-6">
                    <div class="h-40">
                        <div id="editor-incluye"></div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Modals -->
    <!-- Add Attribute Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-2xl w-full max-w-md p-6 transform transition-all scale-100">
            <h3 class="text-lg font-bold text-white mb-4" id="addModalTitle">Agregar</h3>
            <input type="text" id="newAttributeName" class="w-full rounded-lg px-4 py-3 bg-slate-900 border border-slate-600 text-white focus:border-blue-500 outline-none mb-6" placeholder="Nombre...">
            <div class="flex justify-end gap-3">
                <button onclick="closeModal('addModal')" class="px-4 py-2 text-slate-400 hover:text-white transition">Cancelar</button>
                <button onclick="saveAttribute()" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition">Guardar</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-slate-800 rounded-xl border border-slate-700 shadow-2xl w-full max-w-md p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-3xl text-red-500"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">¿Eliminar elemento?</h3>
                <p class="text-slate-400 text-sm" id="deleteMessage">Esta acción no se puede deshacer.</p>
            </div>
            <div class="flex justify-center gap-3">
                <button onclick="closeModal('deleteModal')" class="px-4 py-2 text-slate-400 hover:text-white transition">Cancelar</button>
                <button onclick="executeDelete()" class="px-6 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg font-bold transition">Sí, Eliminar</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-5 right-5 bg-emerald-600 text-white px-6 py-4 rounded-lg shadow-2xl transform translate-y-24 transition-transform duration-300 flex items-center gap-3 z-50">
        <i class="fa-solid fa-circle-check text-xl"></i>
        <div>
            <h4 class="font-bold text-sm" id="toastTitle">¡Producto Guardado!</h4>
            <p class="text-xs text-emerald-100" id="toastMessage">El producto se ha añadido al inventario.</p>
        </div>
    </div>

    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // ... (Previous Quill and Price/Stock JS remains same) ...
        
        // Attribute Management Logic
        let currentType = ''; // 'category' or 'brand'
        let deleteId = 0;

        function openAddModal(type) {
            currentType = type;
            document.getElementById('addModalTitle').innerText = type === 'category' ? 'Nueva Categoría' : 'Nueva Marca';
            document.getElementById('newAttributeName').value = '';
            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('addModal').classList.add('flex');
            document.getElementById('newAttributeName').focus();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        async function saveAttribute() {
            const name = document.getElementById('newAttributeName').value;
            if (!name) return;

            const formData = new FormData();
            formData.append('action', currentType === 'category' ? 'add_category' : 'add_brand');
            formData.append('name', name);

            try {
                const res = await fetch('../backend/manage_attributes.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    // Add to select
                    const selectId = currentType === 'category' ? 'main_category' : 'brand_id';
                    const select = document.getElementById(selectId);
                    const option = new Option(data.data.name, data.data.id);
                    select.add(option);
                    select.value = data.data.id; // Select new item
                    
                    closeModal('addModal');
                    showToast('Éxito', data.message);
                } else {
                    alert(data.message);
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function confirmDelete(type) {
            currentType = type;
            const selectId = type === 'category' ? 'main_category' : 'brand_id';
            const select = document.getElementById(selectId);
            const id = select.value;
            
            if (!id) return alert('Selecciona un elemento para eliminar');
            deleteId = id;

            // Check usage
            const formData = new FormData();
            formData.append('action', 'check_usage');
            formData.append('type', type);
            formData.append('id', id);

            try {
                const res = await fetch('../backend/manage_attributes.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    const count = data.data.count;
                    const msg = count > 1 
                        ? `¡Atención! Tienes <strong>${count} productos</strong> con esta ${type === 'category' ? 'categoría' : 'marca'}.<br>Si la eliminas, esos productos quedarán marcados como "FALTA ${type === 'category' ? 'CATEGORIA' : 'MARCA'}".<br>¿Seguro que quieres eliminarla?` 
                        : (count === 1 ? `Hay <strong>1 producto</strong> con esta ${type === 'category' ? 'categoría' : 'marca'}.<br>Quedará sin asignar.<br>¿Eliminar?` : 'Este elemento no está en uso. ¿Eliminar?');
                    
                    document.getElementById('deleteMessage').innerHTML = msg;
                    document.getElementById('deleteModal').classList.remove('hidden');
                    document.getElementById('deleteModal').classList.add('flex');
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function executeDelete() {
            const formData = new FormData();
            formData.append('action', currentType === 'category' ? 'delete_category' : 'delete_brand');
            formData.append('id', deleteId);

            try {
                const res = await fetch('../backend/manage_attributes.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    // Remove from select
                    const selectId = currentType === 'category' ? 'main_category' : 'brand_id';
                    const select = document.getElementById(selectId);
                    select.remove(select.selectedIndex);
                    
                    closeModal('deleteModal');
                    showToast('Eliminado', data.message);
                } else {
                    alert(data.message);
                }
            } catch (e) {
                console.error(e);
            }
        }

        // Initialize Quill Editors
        var quillDesc = new Quill('#editor-descripcion', {
            theme: 'snow',
            placeholder: 'Describe materiales, especificaciones, peso, dimensiones...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    ['clean']
                ]
            }
        });

        var quillIncluye = new Quill('#editor-incluye', {
            theme: 'snow',
            placeholder: 'Ej: 1x Mira, 1x Paño...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        // Price Formatting Logic
        function formatPrice(input) {
            // Remove non-numeric chars
            let value = input.value.replace(/\D/g, '');
            // Format with dots
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            input.value = value;
        }

        // Stock Toggle Logic
        function toggleStockInput() {
            const isInstock = document.querySelector('input[name="stock_option"][value="instock"]').checked;
            
            const stockContainer = document.getElementById('stockQuantityContainer');
            const stockInput = stockContainer.querySelector('input');
            
            const deliveryContainer = document.getElementById('deliveryDaysContainer');
            const deliveryInput = deliveryContainer.querySelector('input');
            
            if (isInstock) {
                // Show Stock
                stockContainer.style.maxHeight = '100px';
                stockContainer.style.opacity = '1';
                stockContainer.style.marginTop = '0';
                stockInput.disabled = false;

                // Hide Delivery
                deliveryContainer.style.maxHeight = '0';
                deliveryContainer.style.opacity = '0';
                deliveryContainer.style.marginTop = '-10px';
                deliveryInput.disabled = true;
            } else {
                // Hide Stock
                stockContainer.style.maxHeight = '0';
                stockContainer.style.opacity = '0';
                stockContainer.style.marginTop = '-10px';
                stockInput.disabled = true;
                stockInput.value = '';

                // Show Delivery
                deliveryContainer.style.maxHeight = '100px';
                deliveryContainer.style.opacity = '1';
                deliveryContainer.style.marginTop = '0';
                deliveryInput.disabled = false;
            }
        }

        let selectedFiles = [];
        let coverFileName = null; // Track which file is the cover

        function handleFiles(files) {
            const grid = document.getElementById('previewGrid');
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                
                // If no cover set yet, make this one the cover
                if (!coverFileName) {
                    coverFileName = file.name;
                }

                selectedFiles.push(file);
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square rounded-lg overflow-hidden border border-slate-600 group bg-slate-800 transition-all duration-200 hover:border-blue-500';
                    div.dataset.filename = file.name;
                    
                    const isCover = (file.name === coverFileName);
                    
                    div.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        
                        <!-- Overlay Actions -->
                        <div class="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-3 backdrop-blur-sm">
                            
                            <!-- Set Cover Button -->
                            <button type="button" class="text-sm font-medium px-3 py-1 rounded-full flex items-center gap-2 transition-colors ${isCover ? 'bg-yellow-500/20 text-yellow-400 ring-1 ring-yellow-500' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'}" onclick="setCover('${file.name}')">
                                <i class="fa-solid fa-star ${isCover ? 'text-yellow-400' : 'text-slate-400'}"></i>
                                ${isCover ? 'Portada' : 'Hacer Portada'}
                            </button>

                            <!-- Delete Button -->
                            <button type="button" class="text-white hover:text-red-400 transition" onclick="removeFile(this, '${file.name}')">
                                <i class="fa-solid fa-trash-can text-xl"></i>
                            </button>
                        </div>

                        <!-- Cover Indicator (Always visible if cover) -->
                        ${isCover ? '<div class="absolute top-2 right-2 bg-yellow-500 text-slate-900 text-xs font-bold px-2 py-1 rounded shadow-lg"><i class="fa-solid fa-star"></i></div>' : ''}
                    `;
                    grid.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function setCover(fileName) {
            coverFileName = fileName;
            // Re-render indicators
            const grid = document.getElementById('previewGrid');
            Array.from(grid.children).forEach(div => {
                const fName = div.dataset.filename;
                const isCover = (fName === coverFileName);
                
                // Update Button
                const btn = div.querySelector('button:first-child');
                btn.className = `text-sm font-medium px-3 py-1 rounded-full flex items-center gap-2 transition-colors ${isCover ? 'bg-yellow-500/20 text-yellow-400 ring-1 ring-yellow-500' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'}`;
                btn.innerHTML = `<i class="fa-solid fa-star ${isCover ? 'text-yellow-400' : 'text-slate-400'}"></i> ${isCover ? 'Portada' : 'Hacer Portada'}`;

                // Update Indicator Badge
                const existingBadge = div.querySelector('.absolute.top-2.right-2');
                if (isCover && !existingBadge) {
                    div.insertAdjacentHTML('beforeend', '<div class="absolute top-2 right-2 bg-yellow-500 text-slate-900 text-xs font-bold px-2 py-1 rounded shadow-lg"><i class="fa-solid fa-star"></i></div>');
                } else if (!isCover && existingBadge) {
                    existingBadge.remove();
                }
            });
        }

        function removeFile(btn, fileName) {
            // Find the main container which has the data-filename attribute
            const container = btn.closest('[data-filename]');
            if (container) container.remove();
            selectedFiles = selectedFiles.filter(f => f.name !== fileName);
            if (coverFileName === fileName) {
                // If removed file was cover, set new cover to first available
                coverFileName = selectedFiles.length > 0 ? selectedFiles[0].name : null;
                if (coverFileName) setCover(coverFileName);
            }
        }

        const dropZone = document.getElementById('dropZone');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });
        dropZone.addEventListener('drop', handleDrop, false);
        function handleDrop(e) {
            const dt = e.dataTransfer;
            handleFiles(dt.files);
        }

        function addColor() {
            const container = document.getElementById('colorsContainer');
            const div = document.createElement('div');
            div.className = 'flex gap-3 items-center group color-row animate-[fadeIn_0.3s_ease-out]';
            div.innerHTML = `
                <div class="w-8 flex justify-center text-slate-600 cursor-move"><i class="fa-solid fa-grip-vertical"></i></div>
                <input type="text" name="color_names[]" class="flex-1 rounded-lg px-4 py-2 input-tactical text-sm" placeholder="Nombre del color">
                <div class="relative">
                    <input type="color" name="color_hexes[]" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0 overflow-hidden">
                </div>
                <!-- Color Image Input -->
                <div class="relative w-10 h-10 shrink-0">
                    <input type="file" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10 color-img-input" onchange="previewColorImage(this)">
                    <div class="w-full h-full bg-slate-800 rounded border border-slate-600 flex items-center justify-center text-slate-400 hover:text-white hover:border-blue-500 transition overflow-hidden img-preview-box">
                        <i class="fa-solid fa-camera text-xs"></i>
                    </div>
                </div>
                <button type="button" onclick="this.closest('.color-row').remove()" class="w-8 h-8 rounded flex items-center justify-center text-slate-500 hover:text-red-400 hover:bg-slate-800 transition">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            `;
            container.appendChild(div);
        }

        function previewColorImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const box = input.nextElementSibling;
                    box.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                    box.classList.add('border-blue-500');
                }
                reader.readAsDataURL(file);
            }
        }

        async function saveProduct() {
            const form = document.getElementById('productForm');
            const formData = new FormData(form);
            
            // Clean Price (remove dots)
            const rawPrice = formData.get('price');
            if (rawPrice) {
                formData.set('price', rawPrice.replace(/\./g, ''));
            }

            // Handle stock_quantity based on stock_option
            const stockOption = formData.get('stock_option');
            if (stockOption !== 'instock') {
                formData.delete('stock_quantity'); // Remove quantity if not 'instock'
            }
            
            // Append content from Quill editors
            formData.append('descripcion', quillDesc.root.innerHTML);
            formData.append('incluye', quillIncluye.root.innerHTML);

            // Handle Gallery Images
            if (selectedFiles.length > 0) {
                // Find cover image
                let mainFile = selectedFiles.find(f => f.name === coverFileName);
                if (!mainFile) mainFile = selectedFiles[0]; // Fallback

                formData.append('main_image', mainFile);

                // Append others as additional
                selectedFiles.forEach(file => {
                    if (file !== mainFile) {
                        formData.append('additional_images[]', file);
                    }
                });
            }

            // Handle colors and their images
            const colorRows = document.querySelectorAll('.color-row');
            
            // Clear previous color data from formData to avoid duplication/confusion
            formData.delete('color_names[]');
            formData.delete('color_hexes[]');
            
            colorRows.forEach((row, index) => {
                const nameInput = row.querySelector('input[name="color_names[]"]');
                const hexInput = row.querySelector('input[name="color_hexes[]"]');
                const imgInput = row.querySelector('.color-img-input');

                if (nameInput && hexInput) {
                    formData.append(`colors[${index}][name]`, nameInput.value);
                    formData.append(`colors[${index}][hex]`, hexInput.value);
                    
                    if (imgInput && imgInput.files.length > 0) {
                        formData.append(`color_image_${index}`, imgInput.files[0]);
                    }
                }
            });

            const submitBtn = document.querySelector('button[onclick="saveProduct()"]');
            const originalBtnContent = submitBtn.innerHTML;
            
            try {
                // Disable button and show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');

                const response = await fetch('agregar_producto.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('¡Producto Guardado!', 'El producto se ha añadido al inventario.');
                    setTimeout(() => window.location.href = 'productos.php', 2000);
                } else {
                    alert('Error: ' + result.message);
                    // Re-enable button on error
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnContent;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al guardar el producto.');
                // Re-enable button on error
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        }

        function showToast(title, message) {
            const toast = document.getElementById('toast');
            document.getElementById('toastTitle').innerText = title;
            document.getElementById('toastMessage').innerText = message;
            toast.classList.remove('translate-y-24');
            setTimeout(() => toast.classList.add('translate-y-24'), 3000);
        }
    </script>
</body>
</html>
