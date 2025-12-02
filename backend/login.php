<?php
// admin/login.php - Consolidated Login Logic
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session using the centralized secure configuration
require_once __DIR__ . '/secure_session.php';
// Ensure session is started (secure_session.php might not start it automatically if it just defines functions, but usually it does or we call session_start after)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in as admin, redirect
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: productos.php');
    exit;
}

$error = '';
$success = '';

// Check for JSON input (API requests)
$isJsonApi = false;
$jsonData = json_decode(file_get_contents('php://input'), true);
if ($jsonData) {
    $isJsonApi = true;
    $_POST = array_merge($_POST, $jsonData);
    if (!isset($_POST['action'])) {
        $_POST['action'] = 'login'; // Default action for JSON login
    }
}

// Handle Login
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? $_POST['username'] ?? ''); // Accept username for compatibility
    $loginPassword = trim($_POST['password'] ?? '');

    if (empty($email) || empty($loginPassword)) {
        $error = 'Por favor ingrese email y contraseña.';
        if ($isJsonApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
        require_once __DIR__ . '/db.php';
        
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, role, password FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($loginPassword, $user['password'])) {
                // Login Success
                session_regenerate_id(true);
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email']; // Use email from DB
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                    // Set a flag for JS to update localStorage
                    $success = 'admin';
                } else {
                    $success = 'user';
                }

                if ($isJsonApi) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Autenticación exitosa',
                        'redirect' => ($user['role'] === 'admin' ? '/backend/productos.php' : '/'),
                        'user' => [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ]
                    ]);
                    exit;
                }
                
                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                
            } else {
                $error = 'Credenciales incorrectas';
            }
        } catch (Exception $e) {
            $error = 'Error del servidor';
        }
    }

}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wolff Tactical - Admin Login</title>
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
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[url('https://wolfftactical.cl/static/media/Carrusel3.9dd79b765acc207ed368.png')] bg-cover bg-center bg-no-repeat relative">
    
    <div class="absolute inset-0 bg-slate-900/90"></div>

    <div class="relative z-10 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight mb-2">WOLFF<span class="text-blue-500">TACTICAL</span></h1>
            <p class="text-slate-400 text-sm">Bienvenido</p>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="glass-panel rounded-2xl p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6 flex items-center gap-2">
                <i class="fa-solid fa-user text-blue-500"></i> Iniciar Sesión
            </h2>
            
            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded bg-red-500/20 text-red-400 border border-red-500/30 text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-3 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-sm text-center">
                    <i class="fa-solid fa-spinner fa-spin"></i> Acceso concedido. Redirigiendo...
                </div>
                <script>
                    // Update localStorage and redirect
                    localStorage.setItem('currentUser', JSON.stringify({
                        email: '<?php echo $email; ?>',
                        name: '<?php echo htmlspecialchars($user['name'] ?? ''); ?>',
                        role: '<?php echo $success; ?>'
                    }));
                    if ('<?php echo $success; ?>' === 'admin') {
                        localStorage.setItem('isAdminLoggedIn', 'true');
                        setTimeout(() => window.location.href = 'productos.php', 1000);
                    } else {
                        setTimeout(() => window.location.href = '/', 1000);
                    }
                </script>
            <?php else: ?>
                <form method="POST" action="" class="space-y-5" autocomplete="off">
                    <input type="hidden" name="action" value="login">
                    
                    <!-- Fake fields to trick browser autofill -->
                    <input type="text" style="display:none">
                    <input type="password" style="display:none">

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Email</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-slate-500"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" required class="w-full bg-slate-800/50 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder-slate-600" placeholder="nombre@ejemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="off">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Contraseña</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-slate-500"><i class="fa-solid fa-key"></i></span>
                            <input type="password" name="password" required class="w-full bg-slate-800/50 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder-slate-600" placeholder="••••••••" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition shadow-lg shadow-blue-900/50 flex items-center justify-center gap-2">
                        <span>Ingresar</span> <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 text-center pt-6 border-t border-slate-700/50">
                <p class="text-sm text-slate-400">¿No tienes cuenta?</p>
                <button onclick="toggleForms()" class="text-blue-400 hover:text-blue-300 text-sm font-medium mt-1 transition">Registrarse</button>
            </div>
            
            <div class="mt-4 text-center">
                <a href="/" class="inline-flex items-center gap-2 text-slate-500 hover:text-white transition text-sm">
                    <i class="fa-solid fa-arrow-left"></i> Volver a la tienda
                </a>
            </div>
        </div>

        <!-- Register Form (Keep using AJAX for now, simpler) -->
        <div id="registerForm" class="glass-panel rounded-2xl p-8 shadow-2xl hidden">
            <h2 class="text-xl font-semibold text-white mb-6 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-500"></i> Crear Cuenta
            </h2>
            
            <form onsubmit="handleRegister(event)" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nombre Completo</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-500"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="name" required class="w-full bg-slate-800/50 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder-slate-600" placeholder="Ej: Juan Pérez">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Email</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-500"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" required class="w-full bg-slate-800/50 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder-slate-600" placeholder="nombre@ejemplo.com">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Contraseña</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-500"><i class="fa-solid fa-key"></i></span>
                        <input type="password" name="password" required minlength="6" class="w-full bg-slate-800/50 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder-slate-600" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-lg transition shadow-lg shadow-emerald-900/50 flex items-center justify-center gap-2">
                    <span>Crear Cuenta</span> <i class="fa-solid fa-check"></i>
                </button>
            </form>

            <div class="mt-6 text-center pt-6 border-t border-slate-700/50">
                <p class="text-sm text-slate-400">¿Ya tienes cuenta?</p>
                <button onclick="toggleForms()" class="text-blue-400 hover:text-blue-300 text-sm font-medium mt-1 transition">Volver al Login</button>
            </div>
        </div>

        <div id="messageBox" class="mt-4 hidden p-4 rounded-lg text-sm text-center"></div>
    </div>

    <script>
        // Anti-Hacker Script
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
            }
        });

        function toggleForms() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            if (loginForm.classList.contains('hidden')) {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
            } else {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
            }
            document.getElementById('messageBox').classList.add('hidden');
        }

        function showMessage(type, text) {
            const box = document.getElementById('messageBox');
            box.classList.remove('hidden', 'bg-red-500/20', 'text-red-400', 'bg-emerald-500/20', 'text-emerald-400');
            
            if (type === 'error') {
                box.classList.add('bg-red-500/20', 'text-red-400', 'border', 'border-red-500/30');
            } else {
                box.classList.add('bg-emerald-500/20', 'text-emerald-400', 'border', 'border-emerald-500/30');
            }
            
            box.innerHTML = text;
            box.classList.remove('hidden');
        }

        async function handleRegister(e) {
            e.preventDefault();
            document.getElementById('messageBox').classList.add('hidden');
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('../backend/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('success', 'Cuenta creada exitosamente. Por favor inicia sesión.');
                    e.target.reset();
                    const loginEmail = document.querySelector('#loginForm input[name="email"]');
                    if (loginEmail) loginEmail.value = data.email;
                    setTimeout(() => toggleForms(), 2000);
                } else {
                    showMessage('error', result.message || 'Error al registrarse');
                }
            } catch (error) {
                showMessage('error', 'Error de conexión con el servidor');
            }
        }
    </script>
</body>
</html>
