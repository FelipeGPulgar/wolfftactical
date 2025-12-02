<?php
// backend/admin_logout.php
require_once 'secure_session.php'; // Include this to target the correct session (WOLFTACTICAL_SESSION)

// 1. Clear Session Data
$_SESSION = array();

// 2. Clear Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy Session
session_destroy();

// 4. Redirect (Header + Meta + JS for maximum reliability)
header("Location: login.php");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=login.php">
    <script>
        // Force clear localStorage just in case
        localStorage.removeItem('currentUser');
        localStorage.removeItem('isAdminLoggedIn');
        window.location.href = 'login.php';
    </script>
</head>
<body>
    <p>Cerrando sesión... <a href="login.php">Click aquí si no eres redirigido</a></p>
</body>
</html>
