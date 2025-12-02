<?php
// Public endpoint to send contact message to store
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$message = trim($data['message'] ?? '');

if ($message === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y mensaje son requeridos']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit();
}
$domain = strtolower(substr(strrchr($email, '@'), 1));
$allowedDomains = ['gmail.com', 'hotmail.com', 'outlook.com'];
if (!in_array($domain, $allowedDomains)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solo se permiten Gmail/Hotmail/Outlook']);
    exit();
}

$storeEmail = 'iifeeedtactical@gmail.com';
$subject = 'Contacto desde la tienda - WolfTactical';

$lines = [];
if ($name !== '') $lines[] = 'Nombre: ' . $name;
$lines[] = 'Email: ' . $email;
$lines[] = 'Mensaje:';
$lines[] = $message;

$body = implode("\n", $lines) . "\n\n--\nEnviado automáticamente desde WolfTactical";

$headers = 'From: notificaciones@wolftactical.local' . "\r\n" .
           'Reply-To: ' . $email . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

$ok = @mail($storeEmail, $subject, $body, $headers);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo (mail)']);
}
