<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    // 1. Validar formato de email básico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico no es válido.');
    }

    // 2. Validar dominios permitidos (Seguridad anti-spam)
    $allowedDomains = ['gmail.com', 'hotmail.com', 'outlook.com', 'live.com', 'yahoo.com', 'icloud.com'];
    $domain = substr(strrchr($email, "@"), 1);
    if (!in_array(strtolower($domain), $allowedDomains)) {
        throw new Exception('Solo se permiten correos de: Gmail, Outlook, Hotmail, Yahoo o iCloud.');
    }

    // 3. Conectar a BD
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos.');
    }

    // 4. Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => '¡Ya estás suscrito! Gracias.']);
        exit;
    }

    // 5. Insertar
    $stmt = $pdo->prepare("INSERT INTO subscribers (email, created_at) VALUES (?, NOW())");
    $stmt->execute([$email]);

    echo json_encode(['success' => true, 'message' => '¡Suscripción exitosa! Recibirás nuestras ofertas.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
