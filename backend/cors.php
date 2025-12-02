<?php
// Centralized CORS Configuration
// Allowed origins
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:3003',
    'http://localhost:3004',
    'https://wolfftactical.cl',
    'https://www.wolfftactical.cl',
    'https://gestion.wolfftactical.cl',
    'https://www.gestion.wolfftactical.cl',
    'https://a0041238.ferozo.com'
];

// Check if Origin header is present
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Credentials: true");
    }
} else {
    // Fallback for non-browser requests or when Origin is missing
    header("Access-Control-Allow-Origin: *");
}

// Standard CORS headers
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Security Headers (COOP/COEP)
header("Cross-Origin-Opener-Policy: unsafe-none");
// header("Cross-Origin-Embedder-Policy: require-corp"); // Optional, use with caution

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
