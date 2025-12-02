<?php
// Endpoint to send an email with the entire cart contents
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS
require_once 'cors.php';

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
$customerPhone = trim($data['customer_phone'] ?? '');
$items = $data['items'] ?? [];
if (!is_array($items)) { $items = []; }

// Validate items minimal structure
$cleanItems = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    $name = trim($it['name'] ?? '');
    if ($name === '') continue; // skip invalid item
    $quantity = isset($it['quantity']) ? max(1, (int)$it['quantity']) : 1;
    $color = trim($it['color'] ?? '');
    $price = isset($it['price']) ? (float)$it['price'] : 0.0; // unit price
    $cleanItems[] = [
        'name' => $name,
        'quantity' => $quantity,
        'color' => $color,
        'price' => $price
    ];
}

if (empty($cleanItems)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay items válidos en el carrito']);
    exit();
}

// Validate email domain (optional but if provided must pass) gmail/hotmail/outlook
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

$storeEmail = 'interesado@wolfftactical.cl';
$subject = 'Nuevo Interesado - Carrito de Compras';

$lines = [];
$lines[] = 'Nuevo interesado ha enviado su carrito:';
$lines[] = '';
$lines[] = 'Datos del Cliente:';
$lines[] = 'Email: ' . ($customerEmail !== '' ? $customerEmail : 'No informado');
$lines[] = 'Teléfono: ' . ($customerPhone !== '' ? $customerPhone : 'No informado');
$lines[] = '';
$lines[] = 'Detalle del Carrito:';
$total = 0.0;
foreach ($cleanItems as $it) {
    $lineSubtotal = $it['price'] * $it['quantity'];
    $total += $lineSubtotal;
    $lines[] = '- Producto: ' . $it['name'];
    $lines[] = '  Cantidad: ' . $it['quantity'];
    if ($it['color'] !== '') $lines[] = '  Color: ' . $it['color'];
    $lines[] = '  Precio unitario: $' . number_format($it['price'], 0, ',', '.');
    $lines[] = '  Subtotal: $' . number_format($lineSubtotal, 0, ',', '.');
}
$lines[] = 'TOTAL: $' . number_format($total, 0, ',', '.');

$body = implode("\n", $lines) . "\n\n--\nEnviado automáticamente desde WolfTactical";

// Headers simples y directos
$headers = 'From: notificaciones@wolfftactical.cl' . "\r\n" .
           'Reply-To: ' . ($customerEmail !== '' ? $customerEmail : 'no-reply@wolfftactical.cl') . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

// Log para depuración
error_log("Enviando carrito a: $storeEmail desde notificaciones@wolfftactical.cl");

// Intentar envío sin supresión de errores (@) para que salga en el log
$ok = mail($storeEmail, $subject, $body, $headers);

if ($ok) {
    error_log("Correo enviado exitosamente a: $storeEmail");
    echo json_encode(['success' => true]);
} else {
    $error = error_get_last();
    error_log("Fallo al enviar correo. Error: " . print_r($error, true));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo (mail func returned false)', 'debug_error' => $error]);
}
