<?php
// Configuración de errores (ocultar en producción)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/cors.php';

require_once __DIR__ . '/db.php';

session_start();

$response = ['success' => false, 'message' => ''];

try {
    // Solo aceptar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener datos del cuerpo de la solicitud
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Formato de datos inválido');
    }

    // Validar campos
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        throw new Exception('Todos los campos son obligatorios');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }

    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('El email ya está registrado');
    }

    // Hash de la contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insertar usuario
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);
    $userId = $pdo->lastInsertId();

    // Insertar en user_registrations (Modo compatibilidad: Sin user_id)
    $stmt = $pdo->prepare("INSERT INTO user_registrations (email, registered_at) VALUES (?, NOW())");
    $stmt->execute([$email]);

    $response = [
        'success' => true,
        'message' => 'Usuario registrado exitosamente'
    ];
} catch (Exception $e) {
    if (!http_response_code() || http_response_code() < 400) {
        http_response_code(400);
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>