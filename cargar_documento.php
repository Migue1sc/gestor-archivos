<?php
session_start();
include 'includes/config.php';// se incluye el archivo de la conexión a la base

//verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//esta consulta obtiene el nombre del usuario
$user_stmt = $pdo->prepare("SELECT nombre, area_id FROM usuarios WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

//esta consulta obtiene las áreas donde el usuario tiene permisos de escritura
$areas_escritura = [];
if (!$es_admin) {
    $permisos_stmt = $pdo->prepare("
        SELECT pa.area_id AS id, a.nombre 
        FROM permisos_area pa 
        JOIN areas a ON pa.area_id = a.id
        WHERE pa.usuario_id = ? AND pa.escritura = 1
    ");
    $permisos_stmt->execute([$user_id]);
     $areas_escritura = $permisos_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $areas_stmt = $pdo->query("SELECT id, nombre FROM areas");
    $areas_escritura = $areas_stmt->fetchAll(PDO::FETCH_ASSOC);
}

//en esta parte se hace la validación para subir un documento
$error = null;
if (isset($_POST['subir_documento']) && !empty($areas_escritura)) {
    $nombre = trim($_POST['nombre']);
     $tipo = trim($_POST['tipo']);
     $version = $_POST['version'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'] ?: null;
    $area_id = (int)$_POST['area_id'];
    $file = $_FILES['archivo'];

    //verifica que el área seleccionada esté permitida
    $area_permitida = false;
    foreach ($areas_escritura as $area){//se verifica si se tienen permisos de escritura en un área determinada
        if ($area['id'] == $area_id) {
            $area_permitida = true;
            break;
        }
    }

    if (!$area_permitida) {//si no se tienen permisos de scritura en el área imprime el siguente mensaje 
    $error = "No tienes permiso para cargar documentos en el área seleccionada.";
    } elseif (empty($nombre) || empty($tipo)) {
        $error = "El nombre y la descripción del documento son obligatorios.";
    } else {
        //en esta parte de hacen validaciones de lor tipos de archivos permitidos
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        $allowed_mimes = [
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/png', 'image/jpeg'
        ];
        $max_size = 125 * 1024 * 1024;//se da un máximo de carga de archivos de 125 MB
        
        $compress_size = 20 * 1024 * 1024; //en esta parte se establece que se comprime el archivo si este excede los 20 MB

        //en esta parte se verifican errores de carga respecto al tamaño
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
            //en esta parte se valida la extensión
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {//si se detecta alguna extensión no permitida se manda mensaje 
                $error = "Extensión no permitida. Solo se permiten: " . implode(', ', $allowed_extensions) . ".";
            } elseif (!in_array($file['type'], $allowed_mimes)) {//al tratar de cargar un tipo de archivo no definido anteriormente,se muestra el siguente mensaje
                $error = "Tipo de archivo no permitido." . $file['type'];
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $error = "El archivo no fue cargado correctamente.";
            } else {
                //geenera nombre único
                $unique_name = uniqid('doc_', true) . '.' . $file_ext;
                $ruta = "Uploads/" . $unique_name;
                 $es_comprimido = 0;

                //esta parte comprime el archivo si excede 20 MB
                if ($file['size'] > $compress_size) {
                    $es_comprimido = 1;
                     $zip_path = "Uploads/" . $unique_name . '.zip';
                    $zip = new ZipArchive();
                    if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
                        $zip->addFile($file['tmp_name'], $file['name']);
                        $zip->close();
                        $ruta = $zip_path;
                    } else {
                        $error = "Error al crear el archivo comprimido.";
                    }
                } else {
                    //si el archivo no excede los 20 MB,mueve el archivo sin comprimir a la carpeta
                    $ruta .= '.' . $file_ext;
                    if (!move_uploaded_file($file['tmp_name'], $ruta)) {
                    $error = "Error al mover el archivo. Verifica que la carpeta /uploads tenga permisos de escritura.";
                    }
                }

                //si no hay error, guarda en la base base de datos  
                if (!$error) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO documentos (nombre, ruta_archivo, tipo, version, fecha_vencimiento, area_id, usuario_id, es_comprimido) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$nombre, $ruta, $tipo, $version, $fecha_vencimiento, $area_id, $user_id, $es_comprimido]);
                        $documento_id = $pdo->lastInsertId();

                        if (isset($_POST['tramite_id']) && !empty($_POST['tramite_id'])) {
                            $tramite_id = (int)$_POST['tramite_id'];
                             $stmt = $pdo->prepare("INSERT INTO documentos_tramites (tramite_id, documento_id) VALUES (?, ?)");
                             $stmt->execute([$tramite_id, $documento_id]);
                        }
                        header("Location: index.php");
                        exit;
                    } catch (PDOException $e) {
                        $error = "Error al guardar el documento en la base de datos: " . $e->getMessage();
                        //eliminar el archivo si falla la inserción
                        if (file_exists($ruta)) {
                             unlink($ruta);
                        }
                    }
                }
            }
        }
    }
}

//consulta trámites para el formulario
$tramites = $pdo->query("SELECT * FROM tramites")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Documento</title>
<link  rel="stylesheet" href="css/bootstrap.min.css">
    <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cal+Sans&display=swap');
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

        .topnav{
            width: 100%;
            background-color: #383838;
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

        .logout-btn{
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

        .logout{
            filter: invert(100%);
        }

        .topnav .navg:hover, .topnav .navg.active {
            background-color: #11953c;
        color: white;
        }

        .container {
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

        .btn-custom{
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

        .btn-custom:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            
            background-color: #11953c;
            border-color: #11953c;
            color: white;
        }

        .btn-secondary {
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-secondary:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            background-color: #5a6268;
            border-color: #5a6268;
            color: white;
        
        }

        .form-control, .form-select {
            border-radius: 5px;
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

        .bienvenida h3{
            margin: 0;
            color: #343a40;
        }

        .navg{
            font-weight: 600;
        }

        .alert{
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.7);
            
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }

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

            .btn-custom, .btn-secondary{
                min-width: 80px;
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .form-control, .form-select{
                font-size: 0.85rem;
            }

            .alert {
                font-size: 0.85rem;
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
    <div class="bienvenida text-center mb-4">
        <?php if ($es_admin): ?>
        <h3>Bienvenido, <?= htmlspecialchars($user['nombre']) ?>. Puedes cargar documentos en cualquier área.</h3>
        <?php else: ?>
            <h3>Bienvenido, <?= htmlspecialchars($user['nombre']) ?>. Puedes cargar documentos en tus áreas asignadas.</h3>
        <?php endif; ?>
    </div>

    <!--muestra errores si los hay-->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!--botones de navegación-->
    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
        <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
        <?php if ($es_admin): ?>
            <a href="admin.php" class="btn btn-custom">Panel de Administración</a>
        <?php endif; ?>
    </div>

    <!--formulario para cargar documento-->
    <?php if (!empty($areas_escritura)): ?>
        <h3 class="text-center mb-4">Subir Documento</h3>
        <form method="POST" enctype="multipart/form-data" class="row g-3" id="formSubirDocumento">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre del documento</label>
                 <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre del documento" required>
            </div>
            <div class="col-md-6">
                <label for="tipo" class="form-label">Descripción</label>
                <textarea rows="" cols="" name="tipo" id="tipo" class="form-control" placeholder="Breve descripción" required></textarea>
            </div>
            <div class="col-md-6">
                <label for="version" class="form-label">Versión</label>
                <select name="version" id="version" class="form-select" required>
                    <option value="" disabled selected>Seleccionar versión</option>
                     <option value="copia">Copia</option>
                    <option value="certificada">Certificada</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" placeholder="Fecha de vencimiento">
            </div>
            <div class="col-md-6">
                <label for="area_id" class="form-label">Área</label>
                <select name="area_id" id="area_id" class="form-select" required>
                     <option value="" disabled selected>Seleccionar área</option>
                    <?php foreach ($areas_escritura as $area): ?>
                        <option value="<?= htmlspecialchars($area['id']) ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="tramite_id" class="form-label">Trámite (opcional)</label>
                <select name="tramite_id" id="tramite_id" class="form-select">
                    <option value="">Sin trámite</option>
                <?php foreach ($tramites as $tramite): ?>
                    <option value="<?= htmlspecialchars($tramite['id']) ?>"><?= htmlspecialchars($tramite['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label for="archivo" class="form-label">Archivo (PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, máx. 25MB)</label>
                <input type="file" name="archivo" id="archivo" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" required>
                <div id="errorArchivo" class="invalid-feedback"></div>
            </div>
            <div class="col-12 text-center">
                <button type="submit" name="subir_documento" class="btn btn-custom">Subir Documento</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            No tienes permisos para cargar documentos en ninguna área.
        </div>
    <?php endif; ?>
</div>
<script src="js/bootstrap.bundle.min.js"></script>

<!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.container');
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 100);

        //validación del lado del cliente 
        const form = document.getElementById('formSubirDocumento');
        const archivoInput = document.getElementById('archivo');
        const errorArchivo = document.getElementById('errorArchivo');
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        const maxSize = 125 * 1024 * 1024; // 25 MB

        archivoInput.addEventListener('change', () => {
            errorArchivo.textContent = '';
            archivoInput.classList.remove('is-invalid');

            const file = archivoInput.files[0];
            if (!file) {
                errorArchivo.textContent = 'Por favor, selecciona un archivo.';
                archivoInput.classList.add('is-invalid');
                return;
            }

            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExt)) {
                errorArchivo.textContent = 'Extensión no permitida. Solo se permiten: ' + allowedExtensions.join(', ') + '.';
                archivoInput.classList.add('is-invalid');
                return;
            }

            if (file.size > maxSize) {
                errorArchivo.textContent = 'El archivo excede el tamaño máximo permitido (25 MB).';
                archivoInput.classList.add('is-invalid');
                return;
            }
        });

        form.addEventListener('submit', (e) => {
            if (archivoInput.classList.contains('is-invalid')) {
                e.preventDefault();
                errorArchivo.style.display = 'block';
            }
        });
    });
</script>
</body>
</html>