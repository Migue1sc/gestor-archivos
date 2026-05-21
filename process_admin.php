<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['es_admin'] != 1) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $nombre = $_POST['nombre'];
    $area_id = $_POST['area_id'];

    $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, contrasena, nombre, area_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuario, $contrasena, $nombre, $area_id]);
}

$areas = $pdo->query("SELECT * FROM areas")->fetchAll()

?>