<?php
require_once __DIR__ . '/admin_auth.php';
// Enforce admin authentication
require_admin();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'db.php';

$products = [];
try {
    $sql = "SELECT p.*, c.name as category_name, b.name as brand_name,
            (SELECT path FROM product_images WHERE product_id = p.id AND is_cover = 1 ORDER BY sort_order ASC LIMIT 1) as main_image
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            ORDER BY p.id DESC";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

$activePage = 'productos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wolff Tactical - Productos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        slate: { 850: '#151e2e', 900: '#0f172a' },
                        blue: { 600: '#2563eb', 700: '#1d4ed8' }
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
        .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.5); }
    </style>
</head>
<body class="antialiased min-h-screen flex">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 overflow-y-auto">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                    <span>Wolfftactical</span> <i class="fa-solid fa-chevron-right text-xs"></i> <span class="text-blue-400">Productos</span>
                </div>
                <h1 class="text-3xl font-bold text-white tracking-tight">Inventario de Productos</h1>
            </div>
            <div class="flex gap-3 w-full md:w-auto">
                <a href="agregar_producto.php" class="flex-1 md:flex-none px-6 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-500 shadow-lg shadow-blue-900/40 transition flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-plus"></i> Nuevo Producto
                </a>
            </div>
        </header>

        <div class="bg-slate-850 rounded-xl border border-slate-700/50 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800/50 text-slate-400 text-xs uppercase tracking-wider border-b border-slate-700/50">
                            <th class="p-4 font-semibold">Producto</th>
                            <th class="p-4 font-semibold">
                                Categoría
                                <div class="text-xs text-slate-500 font-normal mt-0.5 normal-case">(Marca)</div>
                            </th>
                            <th class="p-4 font-semibold">Precio</th>
                            <th class="p-4 font-semibold">Stock</th>
                            <th class="p-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-700/50">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    <i class="fa-solid fa-box-open text-4xl mb-3 opacity-50"></i>
                                    <p>No hay productos registrados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr class="table-row-hover transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded bg-slate-800 border border-slate-700 overflow-hidden flex-shrink-0">
                                            <?php if ($p['main_image']): ?>
                                                <img src="<?php echo $p['main_image']; ?>" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='https://placehold.co/100x100/1e293b/475569?text=No+Image';">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-600"><i class="fa-solid fa-image"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-white group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($p['model'] ?? 'Sin SKU'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-300">
                                    <?php if (!empty($p['category_name'])): ?>
                                        <?php echo htmlspecialchars($p['category_name']); ?>
                                    <?php else: ?>
                                        <span class="text-red-500 font-bold text-xs uppercase"><i class="fa-solid fa-triangle-exclamation"></i> Falta Categoría</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($p['brand_name'])): ?>
                                        <span class="text-slate-500 text-xs"> (<?php echo htmlspecialchars($p['brand_name']); ?>)</span>
                                    <?php else: ?>
                                        <div class="text-red-500 font-bold text-xs uppercase mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Falta Marca</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 font-medium text-emerald-400">
                                    $<?php echo number_format($p['price'], 0, ',', '.'); ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($p['stock_status'] == 'en_stock'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> En Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Encargo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="editar_producto.php?id=<?php echo $p['id']; ?>" class="p-2 rounded hover:bg-blue-600/20 text-slate-400 hover:text-blue-400 transition" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button onclick="deleteProduct(<?php echo $p['id']; ?>, this)" class="p-2 rounded hover:bg-red-600/20 text-slate-400 hover:text-red-400 transition" title="Eliminar">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="toast" class="fixed bottom-5 right-5 bg-emerald-600 text-white px-6 py-4 rounded-lg shadow-2xl transform translate-y-24 transition-transform duration-300 flex items-center gap-3 z-50">
        <i class="fa-solid fa-circle-check text-xl"></i>
        <div>
            <h4 class="font-bold text-sm" id="toastTitle">¡Éxito!</h4>
            <p class="text-xs text-emerald-100" id="toastMessage">Operación completada.</p>
        </div>
    </div>

    <script>
        async function deleteProduct(id, btn) {
            if (!confirm('¿Estás seguro de eliminar este producto? Esta acción no se puede deshacer.')) return;
            
            // UI Feedback
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.classList.add('cursor-not-allowed', 'opacity-50');

            try {
                const response = await fetch(`eliminar_producto.php?id=${id}`, {
                    method: 'GET'
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('Producto Eliminado', 'El producto ha sido eliminado correctamente.');
                    // Fade out row
                    const row = btn.closest('tr');
                    row.style.transition = 'all 0.5s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                } else {
                    alert('Error: ' + result.message);
                    // Restore button
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    btn.classList.remove('cursor-not-allowed', 'opacity-50');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar el producto.');
                // Restore button
                btn.disabled = false;
                btn.innerHTML = originalContent;
                btn.classList.remove('cursor-not-allowed', 'opacity-50');
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
