<?php
require_once __DIR__ . '/db.php';

$email = 'soylipp@gmail.com';
$newPass = '123456';
$newHash = password_hash($newPass, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$newHash, $email]);
    
    if ($stmt->rowCount() > 0) {
        echo "<h1>Contraseña restablecida con éxito</h1>";
        echo "<p>Usuario: $email</p>";
        echo "<p>Nueva contraseña: $newPass</p>";
        echo "<p>Hash: $newHash</p>";
    } else {
        echo "<h1>No se encontró el usuario</h1>";
        echo "<p>No existe un usuario con el email $email</p>";
        
        // Check if user exists at all
        $stmt = $pdo->query("SELECT * FROM users WHERE email = '$email'");
        $user = $stmt->fetch();
        if ($user) {
            echo "<p>El usuario existe pero la contraseña ya era esa.</p>";
        } else {
            echo "<p>Creando usuario...</p>";
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'client')");
            $stmt->execute(['Soy Lipp', $email, $newHash]);
            echo "<p>Usuario creado exitosamente.</p>";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
