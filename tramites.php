<?php
session_start();
require 'includes/config.php';

//verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//esta parte procesa la subida de documentos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $tramite_id = (int)$_POST['tramite_id'];
    $tipo = $_POST['tipo']; //descripción
    $version = $_POST['version']; //copia o certificado
    $area_id = (int)$_POST['area_id'];
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    //en esta parte se valida el archivo
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    $allowed_mimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg'
    ];
    $max_size = 125 * 1024 * 1024;//se da un maximo de carga de archivos de 125 MB
    $compress_size = 20 * 1024 * 1024;//en esta parte se establece que se comprime el archivo si este exede los 20 MB
    $file = $_FILES['archivo'];

    //aquí se verrifican los errores de carga
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "El archivo excede el tamaño máximo permitido (125 MB).";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = "No se seleccionó ningún archivo.";
                break;
            default:
                $error = "Error al cargar el archivo. Código: " . $file['error'];
                break;
        }
    } elseif ($file['size'] > $max_size) {
        $error = "El archivo excede el tamaño máximo permitido (125 MB).";
    } else {
        //aquí se valida la extensión
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Extensión no permitida. Solo se permiten: " . implode(', ', $allowed_extensions) . ".";
        } elseif (!in_array($file['type'], $allowed_mimes)) {
            $error = "Tipo de archivo no permitido: " . htmlspecialchars($file['type']);
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $error = "El archivo no fue cargado correctamente.";
        } else {
            //se genera un nombre único para evitar sobreescritura de archivos
            $upload_dir = 'Uploads/';
            $unique_name = uniqid('doc_', true) . '.' . $file_ext;
             $file_path = $upload_dir . $unique_name;
             $es_comprimido = 0;

            //se crea el directorio si no existe
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            //aquí se comprime el archivo si excede 20 MB
            if ($file['size'] > $compress_size) {
                $es_comprimido = 1;
                 $zip_path = $upload_dir . $unique_name . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zip_path, ZipArchive::CREATE) === true){
                    $zip->addFile($file['tmp_name'], $file['name']);
                    $zip->close();
                    $file_path = $zip_path;
                    $file_name = basename($zip_path);
                } else {
                    $error = "Error al crear el archivo comprimido.";
                }
            } else {
                //se mueve el archivo sin comprimir
                if (!move_uploaded_file($file['tmp_name'], $file_path)){
                    $error = "Error al mover el archivo. Verifica que la carpeta /Uploads tenga permisos de escritura.";
                } else {
                    $file_name = $unique_name;
                }
            }

            //si no hay errores, se inserta en la base de datos
            if (!isset($error)) {
                try {
                    //se inserta el documento en la tabla documentos
                    $stmt = $pdo->prepare("
                        INSERT INTO documentos (nombre, tipo, version, ruta_archivo, usuario_id, area_id, fecha_vencimiento, es_comprimido)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $file['name'],
                        $tipo,
                        $version,
                        $file_path,
                        $user_id,
                        $area_id,
                        $fecha_vencimiento,
                        $es_comprimido
                    ]);
                    $documento_id = $pdo->lastInsertId();

                    //con esta consulta se relaciona el documento con el trámite
                    $stmt = $pdo->prepare("
                        INSERT INTO documentos_tramites (tramite_id, documento_id)
                        VALUES (?, ?)
                    ");
                     $stmt->execute([$tramite_id, $documento_id]);

                    header("Location: tramites.php?tramite_id=$tramite_id");
                    exit;
                } catch (PDOException $e) {
                    $error = "Error al guardar en la base de datos: " . $e->getMessage();
                }
            }
        }
    }
}
//con esta parte se obtienen todos los trámites
$tramites = $pdo->query("SELECT * FROM tramites")->fetchAll();

//aquí se obtienn las áreas para el formulario de subida
$areas = $pdo->query("SELECT * FROM areas")->fetchAll();

//ver trámite seleccionado
$tramite_seleccionado = null;
$docs_requeridos = [];
$docs_subidos = [];
if (isset($_GET['tramite_id'])) {
    $tramite_id = (int)$_GET['tramite_id'];
    $tramite_seleccionado = $pdo->prepare("SELECT * FROM tramites WHERE id = ?");
    $tramite_seleccionado->execute([$tramite_id]);
    $tramite_seleccionado = $tramite_seleccionado->fetch();

    $docs_requeridos = $pdo->prepare("SELECT * FROM documentos_requeridos WHERE tramite_id = ?");
    $docs_requeridos->execute([$tramite_id]);
    $docs_requeridos = $docs_requeridos->fetchAll();

    $docs_subidos = $pdo->prepare("
        SELECT d.*, u.nombre AS usuario_nombre, a.nombre AS area_nombre
        FROM documentos d 
        JOIN documentos_tramites dt ON d.id = dt.documento_id 
        JOIN usuarios u ON d.usuario_id = u.id 
        JOIN areas a ON d.area_id = a.id 
        WHERE dt.tramite_id = ?
    ");
    $docs_subidos->execute([$tramite_id]);
    $docs_subidos = $docs_subidos->fetchAll();
}

//solo los administradores pueden eliminar documentos
if ($es_admin && isset($_GET['eliminar_id'])) {
    $doc_id = (int)$_GET['eliminar_id'];
    //aquí se elimina la relación en documentos_tramites
    $stmt = $pdo->prepare("DELETE FROM documentos_tramites WHERE documento_id = ?");
    $stmt->execute([$doc_id]);
    // esto elimina el documento
    $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
    $stmt->execute([$doc_id]);
    header("Location: tramites.php?tramite_id=$tramite_id");
    exit;
}

//en esta parte se maneja la descarga y la vista previa
if (isset($_GET['action']) && in_array($_GET['action'], ['download', 'preview']) && isset($_GET['id'])) {
    $doc_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT ruta_archivo, nombre, es_comprimido FROM documentos WHERE id = ?");
    $stmt->execute([$doc_id]);
     $documento = $stmt->fetch();

    if (!$documento){
        die("documento no encontrado.");
    }

    $ruta = $documento['ruta_archivo'];
    $nombre_original = $documento['nombre'];
    $es_comprimido = $documento['es_comprimido'];

    if (!file_exists($ruta)) {
        die("el archivo no existe en el servidor.");
    }

    $mime_types =[
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip'
    ];

    if ($es_comprimido) {
        // en esta parte se descomprime el archivo
        $zip = new ZipArchive();
        if ($zip->open($ruta) === true) {
            $original_file_name = $zip->getNameIndex(0);
            $temp_dir = sys_get_temp_dir() . '/' . uniqid('doc_');
            mkdir($temp_dir);
            $zip->extractTo($temp_dir);
            $zip->close();

            $extracted_file = $temp_dir . '/' . $original_file_name;
            if (!file_exists($extracted_file)) {
                die("error al descomprimir el archivo.");
            }

            //se obtiene la extensión original
            $file_ext = pathinfo($original_file_name, PATHINFO_EXTENSION);
            $mime = $mime_types[$file_ext] ?? 'application/octet-stream';

            //se configuran los headers
            header('Content-Type: ' . $mime);
            header('Content-Disposition: ' . ($_GET['action'] === 'download' ? 'attachment' : 'inline') . '; filename="' . $nombre_original . '.' . $file_ext . '"');
            header('Content-Length: ' . filesize($extracted_file));
            readfile($extracted_file);

            //se limpian los archivos temporales
            unlink($extracted_file);
            rmdir($temp_dir);
            exit;
        } else {
            die("error al abrir el archivo comprimido.");
        }
    } else {
        //enviar archivo sin comprimir
        $file_ext = pathinfo($ruta, PATHINFO_EXTENSION);
        $mime = $mime_types[$file_ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($_GET['action'] === 'download' ? 'attachment' : 'inline') . '; filename="' . $nombre_original . '.' . $file_ext . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trámites</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
        <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->

    <style>
        /*se importa la fuente para el tipo de letra*/
        @import url('https://fonts.googleapis.com/css2?family=Cal+Sans&display=swap');
        
        /*estilo en general*/
        body{
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

        /*estilo de la barra de navegación*/
        .topnav {
            width: 100%;
            background-color: #212529;
            backdrop-filter: blur(100px);
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

        .topnav .navg{
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.5rem;
            border-radius: 5px;
            font-weight: 500;
            font-family: "Cal Sans", sans-serif;
            transition: all 0.3s ease;
        }

        .topnav .navg:hover, .topnav .navg.active{
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

        /*estilo del contenedor principal*/
        .container{
            max-width: 1100px;
            width: 100%;
            margin: 80px 20px 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(30px);
            animation: slideIn 0.8s ease-out;
            padding: 2.5rem;
            min-height: 600px;
        }

        /*estilo del botón personalizado*/
        .btn-custom {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #11953c;
            border-color: #11953c;
            color: white;
            min-width: 90px;
            text-align: center;
            font-size: 0.85rem;
        }

        .btn-custom:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            border-color: #11953c;
            color: white;
        }

        /*estilo del botón personalizado secundario*/
        .btn-custom-sub{
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            
            background-color: #79428d;
            border-color: #79428d;
            color: white;
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

        /*estilo del botón secundario*/
        .btn-secondary {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            min-width: 90px;
            text-align: center;
            font-size: 0.85rem;
        }

        .btn-secondary:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            background-color: #5a6268;
            color: white;
        
        }

        /*estilo de la tabla*/
        .th-head, .td-text {
            text-align: center;
        }

        .btn-danger{
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            min-width: 90px;
            text-align: center;
            font-size: 0.85rem;
        }

        .btn-danger:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            background-color: #c82333;
            border-color: #c82333;
            color: white;
        }

        .logout, .download, .eye, .upload, .trash{
            filter: invert(100%);
        }

        .table{
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            overflow: hidden;
            font-size: 0.9rem;
        }

        .table-dark{
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 0.9rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.4);
        }

        .table-hover tbody tr:hover{
            background-color: rgba(39, 174, 96, 0.2);
        }

        .table td, .table th{
            border: none;
            padding: 0.75rem;
            vertical-align: middle;
        }

        /*estilo de los formulario*/
        .form-control, .form-select {
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #11953c;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);
        }

        /*estilo de la lista*/
        ul{
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 1rem;
            list-style: none;
            font-size: 0.9rem;
        }

        ul li{
            padding: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /*estilo de las acciones*/
        .acciones{
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        /*estilo para el modal*/
        .modal-content {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px);
            
            border-radius: 10px;
            border: none;
        
        }

        .modal-header, .modal-footer {
            border: none;
        }

        /*aquí esta la animación de entrada*/
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

        /*Estos son los estilos responsivos*/
        @media (max-width: 768px){
            .container 
            {
                margin: 60px 10px 10px;
                padding: 1.5rem;
                min-height: 400px;
            }

            .table{
                font-size: 0.85rem;
            }

            .table td, .table th{
                padding: 0.5rem;
            }

            .btn-custom, .btn-secondary, .btn-danger {
                min-width: 80px;
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
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
        <a class="navg" href="index.php">Inicio</a>
        <a class="navg active" href="tramites.php">Trámites</a>
        <a class="navg" href="areas.php">Áreas</a>
        <?php if ($es_admin): ?>
        <a class="navg" href="permisos_area.php">Permisos</a>
        <?php endif; ?>
    </div>
    <a class="logout-btn d-flex align-items-center justify-content-center" href="logout.php">
        <img src="icons/box-arrow-right.svg" alt="cerrar sesión" class="logout me-1">Cerrar Sesión
    </a>
</div>

<div class="container">
    <h1 class="text-center mb-4">Trámites</h1>

    <!--botones-->
    <div class="text-center mb-4">
        <?php if ($es_admin): ?>
            <a href="admin.php" class="btn btn-custom-sub">Panel de administración</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-custom-sub">Volver al inicio</a>
    </div>

    <!--elecciona trámite-->
    <h3 class="text-center mb-4">Seleccionar trámite</h3>
    <form method="GET" class="row g-3 justify-content-center">
        <div class="col-md-6">
            <select name="tramite_id" class="form-select" onchange="this.form.submit()">
                <option value="">Selecciona un trámite</option>
                <?php foreach ($tramites as $tramite): ?>
                <option value="<?= htmlspecialchars($tramite['id']) ?>" <?= isset($_GET['tramite_id']) && $_GET['tramite_id'] == $tramite['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tramite['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!--detalles del trámite seleccionado-->
    <?php if ($tramite_seleccionado): ?>
        <h3 class="text-center mb-4 mt-5">Documentos para <?= htmlspecialchars($tramite_seleccionado['nombre']) ?></h3>
        <h4 class="mb-3">Documentos requeridos</h4>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <ul>
            <?php foreach ($docs_requeridos as $req): ?>
                <li>
                    <span><?= htmlspecialchars($req['nombre_documento']) ?> (<?= $req['obligatorio'] ? 'obligatorio' : 'opcional' ?>)</span>
                    <?php if ($es_admin): ?>
                        <button type="button" class="btn btn-custom btn-sm d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#uploadModal<?= htmlspecialchars($req['id']) ?>">
                        <img src="icons/upload.svg" alt="cargar" class="upload me-1">Cargar
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h4 class="mb-3 mt-4">Documentos subidos</h4>
        <div class="table-responsive" style="background-color: white; border-radius: 10px;">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th class="th-head">Nombre</th>
                        <th class="th-head">Descripción</th>
                        <th class="th-head">Versión</th>
                        <th class="th-head">Subido por</th>
                        <th class="th-head">Área</th>
                        <th class="th-head">Vencimiento</th>
                        <th class="th-head">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($docs_subidos) > 0): ?>
                        <?php foreach ($docs_subidos as $doc): ?>
                            <tr>
                                <td class="td-text"><?= htmlspecialchars($doc['nombre']) ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['tipo']) ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['version']) ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['usuario_nombre']) ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['area_nombre']) ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['fecha_vencimiento'] ?: 'n/a') ?></td>
                                <td class="td-text">
                                    <div class="acciones">
                                        <a href="?action=download&id=<?= htmlspecialchars($doc['id']) ?>" class="btn btn-custom btn-sm d-flex align-items-center">
                                        <img src="icons/file-earmark-arrow-down.svg" alt="descargar" class="download me-1">Descargar
                                        </a>
                                        <a href="?action=preview&id=<?= htmlspecialchars($doc['id']) ?>" target="_blank" class="btn btn-secondary btn-sm d-flex align-items-center">
                                            <img src="icons/eye.svg" alt="vista previa" class="eye me-1">Previa
                                        </a>
                                        <?php if ($es_admin): ?>
                                            <a href="?tramite_id=<?= htmlspecialchars($tramite_seleccionado['id']) ?>&eliminar_id=<?= htmlspecialchars($doc['id']) ?>" class="btn btn-danger btn-sm d-flex align(QWERTYUIOP[]\ASDFGHJKL;ZXCVBNM,./qwertyuiop[]\asdfghjklzxcvbnm,./items-center" onclick="return confirm('¿seguro que deseas eliminar este documento?')">
                                            <img src="icons/trash.svg" alt="eliminar" class="trash me-1">Eliminar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay documentos subidos para este trámite.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!--modal para subir documentos-->
<?php if ($tramite_seleccionado): ?>
    <?php foreach ($docs_requeridos as $req): ?>
        <div class="modal fade" id="uploadModal<?= htmlspecialchars($req['id']) ?>" tabindex="-1" aria-labelledby="uploadModalLabel<?= htmlspecialchars($req['id']) ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadModalLabel<?= htmlspecialchars($req['id']) ?>">Subir documento: <?= htmlspecialchars($req['nombre_documento']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tramite_id" value="<?= htmlspecialchars($tramite_seleccionado['id']) ?>">
                            <div class="mb-3">
                                <label for="tipo" class="form-label">Descripción</label>
                            <input type="text" name="tipo" id="tipo" class="form-control" placeholder="descripción" required>
                            </div>
                            <div class="mb-3">
                                 <label for="archivo<?= htmlspecialchars($req['id']) ?>" class="form-label">Archivo (pdf, jpeg, png, máx. 125mb)</label>
                                 <input type="file" name="archivo" id="archivo<?= htmlspecialchars($req['id']) ?>" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                            <div class="mb-3">
                                <label for="version<?= htmlspecialchars($req['id']) ?>" class="form-label">versión</label>
                                <select name="version" id="version<?= htmlspecialchars($req['id']) ?>" class="form-select" required>
                                     <option value="" disabled selected>selecciona el tipo</option>
                                     <option value="copia">copia</option>
                                     <option value="certificado">certificado</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="area_id<?= htmlspecialchars($req['id']) ?>" class="form-label">Área</label>
                                <select name="area_id" id="area_id<?= htmlspecialchars($req['id']) ?>" class="form-select" required>
                                    <option value="">Selecciona un área</option>
                                    <?php foreach ($areas as $area): ?>
                                    <option value="<?= htmlspecialchars($area['id']) ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_vencimiento<?= htmlspecialchars($req['id']) ?>" class="form-label">Fecha de vencimiento (opcional)</label>
                                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento<?= htmlspecialchars($req['id']) ?>" class="form-control">
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-custom-sub">Subir documento</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="js/bootstrap.bundle.min.js"></script>
    <!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>