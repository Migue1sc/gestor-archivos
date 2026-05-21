<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

include 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($contrasena, $user['contrasena'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['es_admin'] = $user['es_admin'];
        $_SESSION['area_id'] = $user['area_id'];
        
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Credenciales inválidas";
        header("Location: login.php");
        exit();
    }
}
?>