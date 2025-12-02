<?php
// backend/db.php - Hybrid Configuration (Local + Production)

// Detect environment
$isLocal = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if ($isLocal) {
    // Local Environment (Mac)
    $host = '127.0.0.1';
    $primaryDb = 'wolfftactical_local';
    $username = 'root';
    $password = '';
} else {
    // Production Environment (wolfftactical.cl / DonWeb)
    $host = 'localhost'; 
    $primaryDb = 'a0041238_wolfft';
    $username = 'a0041238_wolftac';
    $password = 'WolffTactical2025X';
}

$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $dsn = "mysql:host=$host;dbname=$primaryDb;charset=$charset";
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log error
    error_log("[DB Connect Error] " . $e->getMessage());
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        // Return 500 but with a JSON body so frontend can handle it
        http_response_code(500); 
    }
    
    // Show a cleaner error message
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos: " . $e->getMessage() . " (Host: $host)"
    ]);
    exit;
}
?>
