<?php
require_once __DIR__ . '/db.php';

$email = 'felipegpulgar@gmail.com';
$newPass = '123456';
$newHash = password_hash($newPass, PASSWORD_DEFAULT);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
        $stmt->execute([$newHash, $email]);
        echo "<h1>Admin recuperado</h1>";
        echo "<p>Email: $email</p>";
        echo "<p>Password: $newPass</p>";
        echo "<p>Rol: admin (asegurado)</p>";
    } else {
        // Create if not exists
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['Admin Felipe', $email, $newHash]);
        echo "<h1>Admin Creado</h1>";
        echo "<p>Email: $email</p>";
        echo "<p>Password: $newPass</p>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
