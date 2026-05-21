<?php
include 'includes/config.php'; //se incluye archivo de conexión a la base 
include 'includes/verificar_sesion.php';//se incluye archivo para verificar sesión del usiario

if (empty($_SESSION['es_admin'])) {//si se inicia con credenciales correctas, el sistemal redirige al usauario a "index.php"
    header("Location: index1.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) { //se obtienen los datos el formulario 
    $nombreArea = trim($_POST['nombre']);
    $nombreSubarea = isset($_POST['subarea']) ? trim($_POST['subarea']) : '';

    try {
        $pdo->beginTransaction(); //aquí se inicia una transacción de datos a la base

        //con esta parte el admin puede crear un área principal
        $stmt = $pdo->prepare("INSERT INTO areas (nombre, area_padre_id) VALUES (:nombre, NULL)");
         $stmt->execute(['nombre' => $nombreArea]);
         $area_id = $pdo->lastInsertId();

        //en esta parte el admin puede agergarle una subárea al área pricipal
        if (!empty($nombreSubarea)) {
            $stmt = $pdo->prepare("INSERT INTO areas (nombre, area_padre_id) VALUES (:nombre, :padre_id)");
            $stmt->execute([
                'nombre' => $nombreSubarea,
                'padre_id' => $area_id
            ]);
        }

        $pdo->commit();//esto guarda los cambios en la base 
        header("Location: areas.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();//si hay un error al cargar los datos, esto los revierte
         header("Location: areas.php?error=" . urlencode("Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: areas.php");
    exit;
}
