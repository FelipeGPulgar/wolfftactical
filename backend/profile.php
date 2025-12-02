<?php
// backend/profile.php
require_once __DIR__ . '/secure_session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    // Debug info to diagnose session issues
    $debug = [
        'session_id' => session_id(),
        'cookies_received' => array_keys($_COOKIE),
        'session_vars' => array_keys($_SESSION)
    ];
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'debug' => $debug]);
    exit;
}

$userId = $_SESSION['user_id'];

// Handle GET request to fetch profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$action = $data['action'] ?? '';

try {
    if ($action === 'update_profile') {
        $newName = trim($data['name']);
        $newEmail = trim($data['email']);
        
        if (empty($newName) || empty($newEmail)) {
            throw new Exception("Nombre y Email son obligatorios");
        }

        // Check if email is taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) {
            throw new Exception("El email ya está en uso por otra cuenta");
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$newName, $newEmail, $userId]);

        // Update session
        $_SESSION['user_name'] = $newName;
        $_SESSION['user_email'] = $newEmail;

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
    } 
    elseif ($action === 'change_password') {
        $currentPass = $data['current_password'];
        $newPass = $data['new_password'];
        $confirmPass = $data['confirm_password'];

        if (empty($currentPass) || empty($newPass)) {
            throw new Exception("Todos los campos son obligatorios");
        }

        if ($newPass !== $confirmPass) {
            throw new Exception("Las nuevas contraseñas no coinciden");
        }

        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
             throw new Exception("Usuario no encontrado en la base de datos");
        }

        if (!password_verify($currentPass, $user['password'])) {
            // Debug: Return hash info if verification fails (remove in production)
            // $debugInfo = ['input' => $currentPass, 'stored_hash' => $user['password']];
            throw new Exception("La contraseña actual es incorrecta");
        }

        // Update password
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$newHash, $userId]);
        
        if (!$result) {
             throw new Exception("Error al actualizar la base de datos");
        }

        echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente. Por favor inicia sesión con tu nueva clave.']);
    }
    else {
        throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
