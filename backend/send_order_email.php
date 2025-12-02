<?php
// Public endpoint to send order email to store
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

$customerEmail = trim($data['customer_email'] ?? '');
$productName = trim($data['product_name'] ?? '');
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;
$color = trim($data['color'] ?? '');
$mode = trim($data['mode'] ?? 'instock'); // 'instock' | 'preorder'

if ($productName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'product_name requerido']);
    exit();
}

// Validate customer email domain: gmail.com, hotmail.com, outlook.com
if ($customerEmail !== '') {
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit();
    }
    $domain = strtolower(substr(strrchr($customerEmail, '@'), 1));
    $allowedDomains = ['gmail.com', 'hotmail.com', 'outlook.com'];
    if (!in_array($domain, $allowedDomains)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Solo se permiten Gmail/Hotmail/Outlook']);
        exit();
    }
}

$storeEmail = 'iifeeedtactical@gmail.com';
$subject = 'Consulta/Orden desde la tienda - ' . $productName;

$lines = [];
$lines[] = 'Producto: ' . $productName;
if ($mode === 'instock') {
    $lines[] = 'Modo: En stock';
    if ($quantity !== null) { $lines[] = 'Cantidad: ' . max(1, $quantity); }
} else {
    $lines[] = 'Modo: Por encargo';
}
if ($color !== '') { $lines[] = 'Color: ' . $color; }
if ($customerEmail !== '') { $lines[] = 'Email del cliente: ' . $customerEmail; }

$body = implode("\n", $lines) . "\n\n--\nEnviado automáticamente desde WolfTactical";

$headers = 'From: notificaciones@wolftactical.local' . "\r\n" .
           'Reply-To: ' . ($customerEmail !== '' ? $customerEmail : 'no-reply@wolftactical.local') . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

$ok = @mail($storeEmail, $subject, $body, $headers);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo (mail)']);
}

