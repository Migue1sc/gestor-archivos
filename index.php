<?php
session_start();
require 'includes/config.php';

//esta parte verifica que el usuario haya iniciado sesión correctamente
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//aquí se obtiene el nombre del usuario y su área principal
$user_stmt = $pdo->prepare("SELECT nombre, area_id FROM usuarios WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

//aquí obtenemos el nombre del área principal
$area_stmt = $pdo->prepare("SELECT nombre FROM areas WHERE id = ?");
$area_stmt->execute([$user['area_id']]);
$area = $area_stmt->fetch();

//esta parte verifica las áreas donde el usuario tiene permisos
$areas_permitidas = [];
$permisos_por_area = [];
if (!$es_admin) {
    $permisos_stmt = $pdo->prepare("
        SELECT area_id, lectura, escritura, eliminacion 
        FROM permisos_area 
        WHERE usuario_id = ?
    ");
    $permisos_stmt->execute([$user_id]);
    $permisos = $permisos_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($permisos as $permiso) {
        if ($permiso['lectura'] || $permso['escritura'] || $permiso['eliminacion']) {
            $areas_permitidas[] = $permiso['area_id'];
        }
        $permisos_por_area[$permiso['area_id']] = [
            'lectura' => (int)$permiso['lectura'],
            'escritura' => (int)$permiso['escritura'],
            'eliminacion' => (int)$permiso['eliminacion']
        ];
    }
    if (!isset($permisos_por_area[$user['area_id']])) {
        $areas_permitidas[] = $user['area_id'];
        $permisos_por_area[$user['area_id']] = [
            'lectura' => 1,
            'escrirura' => 0,
            'eliminacion' => 0
        ];
    }
} else {
    $areas_stmt = $pdo->query("SELECT id FROM areas");
    $areas_permitidas = array_column($areas_stmt->fetchAll(), 'id');
    $areas_stmt = $pdo->query("SELECT id FROM areas");
    foreach ($areas_stmt->fetchAll() as $area) {
        $permisos_por_area[$area['id']] = [
            'lectura' => 1,
            'escritura' => 1,
            'eliminacion' => 1
        ];
    }
}

//aquí se obtiene la página actual desde la URL (por ejemplo, ?page=2)
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$docs_por_pagina = 10;
$offset = ($pagina - 1) * $docs_por_pagina;

//en esta parte se cuenta el total de documentos en las áreas permitidas
$total_docs = 0;
if (!empty($areas_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($areas_permitidas), '?'));
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM documentos 
        WHERE area_id IN ($placeholders)
    ");
    $count_stmt->execute($areas_permitidas);
    $total_docs = $count_stmt->fetchColumn();
}

//aquí se calcula el número total de páginas
$total_paginas= ceil($total_docs / $docs_por_pagina);

//esta es la consulta para obtener los documentos de las áreas permitidas con paginación
$docs = [];
if (!empty($areas_permitidas)) {
    $placeholders =implode(',', array_fill(0, count($areas_permitidas), '?'));
    try{
    $documentos= $pdo->prepare("
        SELECT d.*, u.nombre as usuario_nombre, a.nombre as area_nombre
        FROM documentos d 
        LEFT JOIN usuarios u ON d.usuario_id = u.id 
        LEFT JOIN areas a ON d.area_id = a.id
        WHERE d.area_id IN ($placeholders)
        ORDER BY d.area_id, d.fecha_subida DESC
        LIMIT ? OFFSET ?
    ");
    
    $paramIndex= 1;
    foreach($areas_permitidas as $area_id){
        $documentos->bindValue($paramIndex++, $area_id, PDO::PARAM_INT);
    }
    $documentos->bindValue($paramIndex++, $docs_por_pagina, PDO::PARAM_INT);
    $documentos->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $documentos->execute();
    $docs = $documentos->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $e){
    die("Error al obtener documentos: ". $e->getMessage());
}
}


/****************************************************
//esta es una consulta para obtener los documentos de las áreas permitidas
$docs = [];
if (!empty($areas_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($areas_permitidas), '?'));
    $documentos = $pdo->prepare("
        SELECT d.*, u.nombre as usuario_nombre, a.nombre as area_nombre
        FROM documentos d 
        LEFT JOIN usuarios u ON d.usuario_id = u.id 
        LEFT JOIN areas a ON d.area_id = a.id
        WHERE d.area_id IN ($placeholders)
        ORDER BY d.area_id, d.fecha_subida DESC
        
    ");

    //LIMIT 10 OFFSET 0
    $documentos->execute($areas_permitidas);
    $docs = $documentos->fetchAll();
}
****************************************************************/
//esta parte es para poder eliminar el documento
if (isset($_GET['eliminar_id'])) {
    $doc_id = (int)$_GET['eliminar_id'];
    try {
        if ($es_admin) {
            $stmt = $pdo->prepare("DELETE FROM documentos_tramites WHERE documento_id = ?");
            $stmt->execute([$doc_id]);
            $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
            $stmt->execute([$doc_id]);
        } else {
            $doc_stmt = $pdo->prepare("SELECT area_id FROM documentos WHERE id = ?");
            $doc_stmt->execute([$doc_id]);
            $doc = $doc_stmt->fetch();
            
            if ($doc && isset($permisos_por_area[$doc['area_id']]) && $permisos_por_area[$doc['area_id']]['eliminacion'] == 1) {
                $stmt = $pdo->prepare("DELETE FROM documentos_tramites WHERE documento_id = ?");
                $stmt->execute([$doc_id]);
                $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
                $stmt->execute([$doc_id]);
            }
        }
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        die("Error al eliminar documento: " . $e->getMessage());
    }
}

//aquí se maneja la decarga y la vista previa<<
if (isset($_GET['action']) && in_array($_GET['action'], ['download', 'preview']) && isset($_GET['id'])) {
    $doc_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT ruta_archivo, nombre, es_comprimido FROM documentos WHERE id = ?");
    $stmt->execute([$doc_id]);
    $documento = $stmt->fetch();

    if (!$documento) {
        die("Documento no encontrado.");
    }

    $ruta = $documento['ruta_archivo'];
    $nombre_original = $documento['nombre'];
    $es_comprimido = $documento['es_comprimido'];

    if (!file_exists($ruta)) {
        die("El archivo no existe en el servidor.");
    }

    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg'
    ];

    if ($es_comprimido) {
        //esta parte descomprime el archivo 
        $zip = new ZipArchive();
        if ($zip->open($ruta) === true) {
            //en ests parte se berifica que el zip contiene un solo archivo
            $original_file_name = $zip->getNameIndex(0);
            $temp_dir = sys_get_temp_dir() . '/' . uniqid('doc_');
            mkdir($temp_dir);
            $zip->extractTo($temp_dir);
            $zip->close();

            $extracted_file = $temp_dir . '/' . $original_file_name;
            if (!file_exists($extracted_file)) {
                die("Error al descomprimir el archivo.");
            }

            //aquí se obtiene la extensión original
            $file_ext = pathinfo($original_file_name, PATHINFO_EXTENSION);
            $mime = $mime_types[$file_ext] ?? 'application/octet-stream';

            //se configuran los headers<<<<
            header('Content-Type: ' . $mime);
            header('Content-Disposition: ' . ($_GET['action'] === 'download' ? 'attachment' : 'inline') . '; filename="' . $nombre_original . '.' . $file_ext . '"');
            header('Content-Length: ' . filesize($extracted_file));
            readfile($extracted_file);

            //se limpia un archivo temporal
            unlink($extracted_file);
            rmdir($temp_dir);
            exit;
        } else {
            die("Error al abrir el archivo comprimido.");
        }
    } else {
        //en esta parte se envia un archivo si comprimir a la carpeda de destino
        $file_ext = pathinfo($ruta, PATHINFO_EXTENSION);
        $mime = $mime_types[$file_ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($_GET['action'] === 'download' ? 'attachment' : 'inline') . '; filename="' . $nombre_original . '.' . $file_ext . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}

//consulta para obtener las notificaciones
$stmt = $pdo->prepare("
    SELECT n.*, d.nombre AS documento_nombre 
    FROM notificaciones n 
    JOIN documentos d ON n.documento_id = d.id 
    WHERE n.usuario_id = ? 
    ORDER BY n.fecha_envio DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$notificaciones = $stmt->fetchAll();

$tramites = $pdo->query("SELECT * FROM tramites")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    <link  rel="stylesheet" href="css/bootstrap.min.css">
     <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"> -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cal+Sans&display=swap');
        body {
            min-height: 100vh;
            background: url('image/background_inap.jpg') center/cover no-repeat fixed;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: "Cal Sans", sans-serif;
            font-weight: 300;
        }

        .topnav {
            width: 100%;
            background-color: #212529;
            backdrop-filter: blur(30px);
            padding: 0.5rem 1rem;
            position: fixed;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .topnav .nav-links {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .topnav .navg {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.5rem;
            border-radius: 5px;
            font-weight: 500;
            font-family: "Cal Sans", sans-serif;
            transition: all 0.3s ease;
        }

        .topnav .navg:hover, .topnav .navg.active {
            background-color: #11953c;
            color: white;
        }

        .logout-btn {
            background-color: #dc3545;
            color: white !important;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: #c82333;
            color: white !important;
        }

        .notification-item {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .notification-item:hover {
            transform: translateX(5px);
        }
        .notification-vencido {
            border-left-color: #dc3545;
        }
        .notification-proximo {
            border-left-color: #ffc107;
        }

        .container {
            max-width: 1100px;
            width: 100%;
            margin: 80px 20px 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            animation: slideIn 0.8s ease-out;
            padding: 2.5rem;
            min-height: 600px;
        }
        
        .btn-custom {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #11953c;
            border-color: #11953c;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            color: white;
        }

        .btn-custom-sub {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #79428d;
            border-color: #79428d;
            color: white;
            display: inline-block;
            min-width: 90px;
            font-size: 0.90rem;
            text-align: center;
        }

        .btn-custom-sub:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            color: white;
        }

        .btn-secondary {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #6c757d;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            background-color: #5a6268;
            color: white;
        }

        .btn-primary {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #0d6efd;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 134, 193, 0.4);
            background-color: #095cd8;
            color: white;
        }

        .th-head, .td-text{
            text-align: center;
        }

        .danger, .warning, .succes{
            border-radius: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .danger{
            background-color: #dc3545;
            color: white;
        }

        .warning{
            background-color: #ffc107; 
            -webkit-text-stroke: 0.02rem white;
        }

        .succes{
            background-color: #11953c;
            color: white;
        }

        .btn-danger {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #dc3545;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            background-color: #c82333;
            color: white;
        }

        .btn-outline-success {
            border-radius: 5px;
            font-weight: 400;
        }

               .cargar {
  display: flex;
  justify-content: center;
  /* width: 100%; */
  /* height: 100px; */
  /* border: 1px solid black; */
}

.boton {
  /*estilos para el botón */
  padding: 0.4rem 0.8rem;
}

        .table {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            overflow: hidden;
            font-size: 0.9rem;
        }

        .table-dark {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 0.9rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.4);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(39, 174, 96, 0.2);
        }
        

        .table td, .table th {
            border: none;
            padding: 0.75rem;
            vertical-align: middle;
        }

        .form-control {
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #11953c;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);
        }

        .form-select {
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .form-select:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #11953c;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);
        }

        ul {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 1rem;
            list-style: none;
            font-size: 0.9rem;
        }

        ul li {
            padding: 0.5rem 0;
        }

        .bienvenida h3 {
            margin: 0;
            color: #343a40;
            text-align: center;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px);
            border-radius: 10px;
            border: none;
        }

        .modal-header, .modal-footer {
            border: none;
        }

        .acciones {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

 

/************************************************************** */
.pagination {
    margin-top: 1.5rem;
    font-size: 0.9rem;
}

.page-link {
    border-radius: 5px;
    color: #11953c;
    background-color: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.page-link:hover {
    background-color: #11953c;
    color: white;
    border-color: #11953c;
}

.page-item.active .page-link {
    background-color: #11953c;
    border-color: #11953c;
    color: white;
}

.page-item.disabled .page-link {
    background-color: rgba(255, 255, 255, 0.5);
    border-color: rgba(0, 0, 0, 0.1);
    color: #6c757d;
    cursor: not-allowed;
}

.logout, .download, .eye, .update, .trash{
    filter: invert(100%);
}
/********************************** */

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 60px 10px 10px;
                padding: 1.5rem;
                min-height: 400px;
            }

            .table {
                font-size: 0.85rem;
            }

            .table td, .table th {
                padding: 0.5rem;
            }

            .btn-custom, .btn-secondary, .btn-danger {
                min-width: 80px;
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
                border-radius: 5px;
            }

            .acciones {
                gap: 0.4rem;
            }
        }
    </style>
</head>
<body>
<div class="topnav">
    <div class="nav-links">
        <a class="navg active" href="index.php">Inicio</a>
        <a class="navg" href="tramites.php">Trámites</a>
        <a class="navg" href="areas.php">Áreas</a>
        <?php if ($es_admin): ?>
            <a class="navg" href="permisos_area.php">Permisos</a>
        <?php endif; ?>
    </div>
    <a class="logout-btn d-flex align-items-center justify-content-center" href="logout.php">
        <img src="icons/box-arrow-right.svg" alt="Repetir" class="logout me-1">Cerrar Sesión
    </a>
</div>

<div class="container">
    <div class="bienvenida">
        <?php if ($es_admin): ?>
            <h3>Bienvenido, <?= htmlspecialchars($user['nombre'] ?? '') ?>.</h3><br>
        <?php else: ?>
            <h3>Bienvenido, <?= htmlspecialchars($user['nombre'] ?? '') ?>. Estos son los documentos que puedes ver en tus áreas asignadas.</h3><br>
        <?php endif; ?>
    </div>

    <!--sección de notificaciones-->
    <div class="card mb-4 border-0 shadow-sm" style="border-radius: 10px">
        <div class="card-header bg-dark text-white">
            <h3 class="mb-0 text-center">Notificaciones Recientes</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($notificaciones): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notificaciones as $notif): ?>
                        <div class="list-group-item notification-item <?= $notif['tipo'] === 'vencido' ? 'notification-vencido' : 'notification-proximo' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($notif['documento_nombre']) ?></h6>
                                    <span class="badge <?= $notif['tipo'] === 'vencido' ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                        <?= $notif['tipo'] === 'vencido' ? 'Vencido' : 'Próximo a vencer' ?>
                                    </span>
                                    <small class="text-muted d-block mt-1">Estado: <?= $notif['estado'] ?></small>
                                </div>
                                <small class="text-muted"><?= $notif['fecha_envio'] ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-4">
                    <div class="alert alert-info mb-0">No hay notificaciones recientes.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!--barra de búsqueda-->
    <div class="mb-4">
        <input type="text" id="buscador" class="form-control" placeholder="Buscar documentos por nombre, tipo, quien lo subi, vencimiento o área..." autocomplete="off">
    </div>

    <div class="d-flex d-grid gap-5 mb-4 text-center col-7 mx-auto cargar d-flex align-items-center justify-content-center">
        <?php if ($es_admin): ?>
            <a href="admin.php" class="btn btn-custom-sub">Panel de Administración</a>
            <button type="button" class="btn btn-custom-sub" data-bs-toggle="modal" data-bs-target="#agregarCategoriaModal">
                Agregar Área
            </button>
        <?php endif; ?>
        <a href="cargar_documento.php" class="btn btn-custom-sub boton">Cargar documento</a>
    </div>

    <div class="table-responsive mb-5 " style="background-color: white; border-radius: 10px;" >
        <table class="table table-striped table-hover" id="tablaDocumentos">
            <thead class="table-dark">
                <tr>
                    <th class="th-head">Nombre</th>
                    <th class="th-head">Descripción</th>
                    <th class="th-head">Versión</th>
                    <th class="th-head">Fecha de subida</th>
                    <th class="th-head">Vencimiento</th>
                    <th class="th-head">Subido por</th>
                    <th class="th-head">Área</th>
                    <th class="th-head">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($docs) > 0): ?>
                    <?php foreach ($docs as $doc): ?>
                        <tr>
                            <td class="td-text"><?= htmlspecialchars($doc['nombre']) ?></td>
                            <td class="td-text"><?= htmlspecialchars($doc['tipo']) ?></td>
                            <td class="td-text"><?= htmlspecialchars($doc['version']) ?></td>
                            <td class="td-text"><?= htmlspecialchars($doc['fecha_subida']) ?></td>
                            <td class="td-text">
                                <?php if(!empty($doc['fecha_vencimiento'])): ?>
                                        <?php 
                                            $fecha_vencimiento = strtotime($doc['fecha_vencimiento']);
                                            $hoy = time();
                                            $diferencia = $fecha_vencimiento - $hoy;
                                            $dias_para_vencer = floor($diferencia / (60 * 60 * 24));
                                            
                                            if($fecha_vencimiento < $hoy): ?>
                                                <span class="danger">Vencido</span>
                                            <?php elseif($dias_para_vencer <= 30): ?>
                                                <span class="warning">Vence en: <?= $dias_para_vencer ?> días</span>
                                            <?php else: ?>
                                                <span class="succes">Vigente</span>
                                            <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ">No vence</span>
                                    <?php endif; ?>
                                    
                                    <br><?= htmlspecialchars($doc['fecha_vencimiento'] ?: 'N/A') ?></td>
                            <td class="td-text"><?= htmlspecialchars($doc['usuario_nombre']) ?></td>
                            <td class="td-text"><?= htmlspecialchars($doc['area_nombre']) ?></td>
                            <td class="td-text">
                                <div class="acciones d-grid gap-2">
                                    <?php if ($permisos_por_area[$doc['area_id']]['lectura']): ?>
                                        <a href="?action=download&id=<?= $doc['id'] ?>" class="btn btn-custom btn-sm d-flex align-items-center justify-content-center btn-block btn-lg"><img src="icons/file-earmark-arrow-down.svg" alt="Descargar" class="download me-1">Descargar</a>
                                        <a href="?action=preview&id=<?= $doc['id'] ?>" target="_blank" class="btn btn-secondary btn-sm align-items-center justify-content-center  btn-block btn-lg"><img src="icons/eye.svg" alt="Vista previa" class="eye me-1">Previa</a>
                                    <?php endif; ?>
                                    <?php if ($es_admin || (isset($permisos_por_area[$doc['area_id']]) && $permisos_por_area[$doc['area_id']]['escritura'] == 1)): ?>
                                    <a href="actualizar_documento.php?id=<?= $doc['id'] ?>" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center btn-block btn-lg"><img src="icons/repeat.svg" alt="Actualizar" class="update me-1">Actualizar </a>
                                    <?php endif; ?> 
                                    <?php if ($es_admin || (isset($permisos_por_area[$doc['area_id']]) && $permisos_por_area[$doc['area_id']]['eliminacion'] == 1)): ?>
                                        <a href="?eliminar_id=<?= $doc['id'] ?>" class="btn btn-danger btn-sm d-flex align-items-center justify-content-center btn-block btn-lg" onclick="return confirm('¿Seguro que deseas eliminar este documento?')"><img src="icons/trash.svg" alt="Eliminar" class="trash me-1">Eliminar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No hay documentos disponibles en tus áreas asignadas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!------------------------------------------------>
    <!--controles de paginación-->


<?php if ($total_paginas > 1): ?>
    <nav aria-label="Paginación de documentos">
        <ul class="pagination justify-content-center">
            <!--botón Anterior-->
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina - 1 ?>" aria-label="Anterior">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>

            <!--números de página-->
            <?php
            $rango = 2; //para mostrar 2 páginas antes y después de la página actual
            $start = max(1, $pagina - $rango);
            $end = min($total_paginas, $pagina + $rango);

            //para mostrar puntos suspensivos si hay más páginas antes
            if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="?pagina=1">1</a></li>
                <?php if ($start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endif; ?>

            <!--páginas numeradas-->
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <!--para moostrar puntos suspensivos si hay más páginas después-->
            <?php if ($end < $total_paginas): ?>
                <?php if ($end < $total_paginas - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item"><a class="page-link" href="?pagina=<?= $total_paginas ?>"><?= $total_paginas ?></a></li>
            <?php endif; ?>

            <!--botón siguiente-->
            <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina + 1 ?>" aria-label="Siguiente">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
</div>

<!--modal para agregar área-->
<?php if ($es_admin): ?>
<div class="modal fade" id="agregarCategoriaModal" tabindex="-1" aria-labelledby="agregarCategoriaModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agregarCategoriaModalLabel">Nueva Área</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="agregar_area.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Área Principal</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="subarea" class="form-label">Nombre de Subárea (opcional)</label>
                        <input type="text" class="form-control" id="subarea" name="subarea">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-custom">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="js/bootstrap.bundle.min.js"></script>

 <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.container');
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 100);

        //aquí esta la lógica para el buscador
        const buscador = document.getElementById('buscador');
        const tabla = document.getElementById('tablaDocumentos');
        const filas = tabla.getElementsByTagName('tr');

        buscador.addEventListener('input', () => {
            const termino = buscador.value.toLowerCase();
            for (let i = 0; i < filas.length; i++) {
                const celdas = filas[i].getElementsByTagName('td');
                let coincide = false;
                //esta parte busca en las filas de la tabla nombre 0, tipo 1, vencimiento 4, subido por 5, área 6
                if (celdas.length > 0) {
                    for (let j of [0, 1, 4, 5, 6]) {
                        if (celdas[j] && celdas[j].textContent.toLowerCase().includes(termino)) {
                            coincide = true;
                            break;
                        }
                    }
                    filas[i].style.display = coincide ? '' : 'none';
                }
            }
        });
    });
</script>
</body>
</html>