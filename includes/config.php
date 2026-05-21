<?php
$db_host = '';
$db_name = '';
$db_user = '';
$db_pass = '';

//$smtp_host = 'smtp.gmail.com';
//$smtp_username = ''; //correo al que quieres que lleguen las notificaciones
//$smtp_password = ''; //agregar contraseña
//$smtp_port = 587;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>