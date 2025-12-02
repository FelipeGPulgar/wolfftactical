<?php
// File: backend/notificaciones.php

// --- INICIO: Habilitar errores para depuración ---
// IMPORTANT: Disable display_errors in production!
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production
ini_set('log_errors', 1); // Log errors even if not displayed
// Consider setting a specific log file:
// ini_set('error_log', '/path/to/your/php-error.log');
// --- FIN: Depuración ---

// Start session BEFORE any output for session check
session_start();

// --- Configuración CORS Dinámica ---
require_once 'cors.php'; // Crucial for 'include' credentials

// --- Manejo OPTIONS (Preflight) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(); // Stop script execution for OPTIONS
}

// --- Establecer Content-Type JSON para la respuesta ---
// Must be AFTER potential exit() for OPTIONS
header("Content-Type: application/json");

// --- Conexión DB y Autenticación ---
require_once 'db.php'; // Ensure this defines your $pdo connection

// Asegurar tabla de notificaciones (evitar 500 si no existe)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message VARCHAR(500) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        duration INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {
    // No bloquear por esto, sólo registrar
    error_log('[notifications bootstrap] ' . $e->getMessage());
}

// Verifica la sesión de administrador
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'No autorizado. Por favor, inicie sesión.']);
    exit();
}

// --- Lógica para obtener notificaciones ---
try {
    // Select necessary columns. Order by most recent. Limit results for performance.
    $stmt = $pdo->query("SELECT id, message, type, created_at FROM notifications ORDER BY created_at DESC LIMIT 50");

    if (!$stmt) {
        // Log detailed error if query fails
        $errorInfo = $pdo->errorInfo();
        error_log("Error preparing/executing notification query: " . ($errorInfo[2] ?? 'Unknown PDO error'));
        throw new PDOException("Error al ejecutar la consulta de notificaciones.");
    }

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the structure expected by React
    echo json_encode(['success' => true, 'data' => $notifications]);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("PDOException fetching notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al obtener notificaciones.']);
} catch (Exception $e) {
    // Catch any other unexpected errors
    http_response_code(500);
    error_log("General Exception fetching notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
}

// Script ends here naturally
?>
