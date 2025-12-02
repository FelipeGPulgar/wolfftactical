<script src="../static/js/security.js"></script>
<aside class="w-64 bg-slate-850 border-r border-slate-700 hidden lg:flex flex-col fixed h-full z-10">
    <div class="p-6 flex items-center gap-3 border-b border-slate-700/50">
        <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center font-bold text-white">W</div>
        <span class="font-bold text-lg tracking-wide">WOLFF<span class="text-blue-500">TACTICAL</span></span>
    </div>
    
    <nav class="flex-1 p-4 space-y-2 mt-4">
        <!-- Replaced Dashboard with Producto -->
        <a href="productos.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?php echo ($activePage == 'productos') ? 'bg-blue-600/10 text-blue-400 border border-blue-600/20' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
            <i class="fa-solid fa-boxes-stacked w-5 text-center"></i> Producto
        </a>
    </nav>

    <div class="p-4 border-t border-slate-700/50">
        <a href="admin_logout.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-red-400 transition-colors mt-auto border-t border-slate-800">
            <i class="fa-solid fa-right-from-bracket w-5"></i> Cerrar Sesión
        </a>
    </div>
</aside>
