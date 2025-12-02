<?php
// backend/admin_auth.php
// Centralized admin authentication and protection utilities

require_once __DIR__ . '/secure_session.php';
require_once __DIR__ . '/security.php';

// Apply security headers for admin pages
SecurityHeaders::setHeaders();

// Helper to detect AJAX requests
function is_ajax_request() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) return true;
    return false;
}

// Require admin session for pages (redirect to SPA root if not authenticated)
function require_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Destroy any existing session for safety
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        if (is_ajax_request()) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        } else {
            // Redirect to login
            header('Location: login.php');
            exit;
        }
    }
}

// Require admin for API endpoints (return 401 JSON)
function require_admin_api() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json', true, 401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
}

?>
