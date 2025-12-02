<?php
// backend/reset_password_force.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

$email = 'iifeeedtactical@gmail.com';
$newPassword = '123456';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

echo "<!DOCTYPE html><html><head><title>Reset Password</title><style>body{font-family:sans-serif;padding:2rem;background:#111;color:#eee;}</style></head><body>";

try {
    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Update existing user
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
        $stmt->execute([$hash, $email]);
        echo "<h2 style='color:#4ade80'>Contraseña Actualizada</h2>";
        echo "<p>El usuario <strong>$email</strong> existe.</p>";
        echo "<p>Su contraseña ha sido cambiada a: <strong>$newPassword</strong></p>";
        echo "<p>Rol asegurado como: <strong>admin</strong></p>";
    } else {
        // 3. Create new user if not exists
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['Admin Tactical', $email, $hash]);
        echo "<h2 style='color:#4ade80'>Usuario Creado</h2>";
        echo "<p>El usuario <strong>$email</strong> no existía.</p>";
        echo "<p>Ha sido creado con la contraseña: <strong>$newPassword</strong></p>";
        echo "<p>Rol: <strong>admin</strong></p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:#f87171'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><hr><br>";
echo "<p>Por favor, intenta iniciar sesión ahora en <a href='login.php' style='color:#60a5fa'>login.php</a></p>";
echo "</body></html>";
?>
