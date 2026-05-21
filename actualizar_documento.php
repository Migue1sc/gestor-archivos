<?php
// CABECERAS DE SEGURIDAD HTTP 
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// INICIO DE SESIÓN Y CONFIGURACIONES 
session_start();
require 'includes/config.php';

// PROTECCIÓN CSRF (Cross-Site Request Forgery) 
// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit; 
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0; 

// OBTENER INFORMACIÓN DEL USUARIO
// Preparar y ejecutar consulta para obtener datos del usuario actual
$user_stmt = $pdo->prepare("SELECT nombre, area_id FROM usuarios WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC); 

$error = null;
$documento = null; 

// GESTIÓN DE DOCUMENTOS - OBTENER DOCUMENTO
if (isset($_GET['id'])) {
    $documento_id = (int)$_GET['id'];
    
    $doc_stmt = $pdo->prepare("
        SELECT d.*, dt.tramite_id 
        FROM documentos d 
        LEFT JOIN documentos_tramites dt ON d.id = dt.documento_id 
        WHERE d.id = ?
    ");
    $doc_stmt->execute([$documento_id]);
    $documento = $doc_stmt->fetch(PDO::FETCH_ASSOC); 

    // Si se encontró el documento
    if ($documento) {
        $area_stmt = $pdo->prepare("SELECT nombre FROM areas WHERE id = ?");
        $area_stmt->execute([$documento['area_id']]);
        $area = $area_stmt->fetch(PDO::FETCH_ASSOC);
        
        $documento['area_nombre'] = $area ? $area['nombre'] : 'Área desconocida';

        // VERIFICACIÓN DE PERMISOS SOBRE EL DOCUMENTO        
        // Consultar permisos de escritura del usuario en el área del documento
        $permiso_stmt = $pdo->prepare("
            SELECT escritura 
            FROM permisos_area 
            WHERE usuario_id = ? AND area_id = ?
        ");
          $permiso_stmt->execute([$user_id, $documento['area_id']]);
          $permiso = $permiso_stmt->fetch(PDO::FETCH_ASSOC);

        // El usuario tiene permiso si es admin O tiene permiso de escritura
        $tiene_permiso = $es_admin || ($permiso && $permiso['escritura'] == 1);
        
        if (!$tiene_permiso) {
            $error = "No tienes permiso para actualizar este documento.";
            $documento = null; 
        }
    } else {
        $error = "Documento no encontrado.";
    }
}

// OBTENER LISTADO DE TRÁMITES PARA EL FORMULARIO 
$tramites_stmt = $pdo->prepare("SELECT * FROM tramites");
 $tramites_stmt->execute();
$tramites = $tramites_stmt->fetchAll(PDO::FETCH_ASSOC);

// PROCESAMIENTO DEL FORMULARIO DE ACTUALIZACIÓN 
if (isset($_POST['actualizar_documento']) && $documento) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.";
    } else {

        // SANEAMIENTO Y VALIDACIÓN DE DATOS DEL FORMULARIO 
        $nombre = htmlspecialchars(trim($_POST['nombre']));
        $tipo = htmlspecialchars(trim($_POST['tipo']));
        
        $version = in_array ($_POST['version'], ['copia', 'certificada']) ? $_POST['version'] : 'copia';
        
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

        $file = $_FILES ['archivo'];
        $nuevo_archivo = false; 
        $ruta = $documento['ruta_archivo']; 
        $es_comprimido = $documento['es_comprimido'];

        // CONFIGURACIÓN PARA VALIDACIÓN DE ARCHIVOS        
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        $allowed_mimes = [
            'application/pdf', 
            'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/png', 
            'image/jpeg'
        ];
        
        $max_size = 125 * 1024 * 1024; 
        $compress_size = 20 * 1024 * 1024; 

        // VALIDACIÓN DEL ARCHIVO SUBIDO (si existe)         
        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $nuevo_archivo = true;
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = "El archivo excede el tamaño máximo permitido (125 MB).";
                        break;
                    default:
                        $error = "Error al subir el archivo. Código: {$file['error']}";
                        break;
                }
            } 
            elseif ($file['size'] > $max_size) {
                $error = "El archivo excede el tamaño máximo permitido (125 MB).";
            } else {
                // Obtener extensión y tipo
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);

                if (!in_array($file_ext, $allowed_extensions)) {
                      $error = "Extensión no permitida.";
                } elseif (!in_array($mime_type, $allowed_mimes)) {
                $error = "Tipo MIME no permitido.";
                } 
                elseif (!is_uploaded_file($file['tmp_name'])) {
                    $error = "Archivo inválido.";
                } else {

                    // PROCESAMIENTO DEL NUEVO ARCHIVO
                    $nuevo_archivo = true;
                    
                    // Eliminar archivo anterior si existe
                     if (file_exists($ruta)) {
                        unlink($ruta);
                    }

                    // Generar nombre único para el nuevo archivo
                    $unique_name = uniqid('doc_', true);
                    $ruta = "Uploads/" . $unique_name;
                    $es_comprimido = 0; 

                    // Comprimir si supera el tamaño límite
                    if ($file['size'] > $compress_size) {
                        $es_comprimido = 1;
                        $zip_path = $ruta . '.zip';
                        $zip = new ZipArchive();
                        if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
                            $zip->addFile($file['tmp_name'], $file['name']);
                            $zip->close();
                            $ruta = $zip_path;
                        } else {
                            $error = "Error al comprimir el archivo.";
                        }
                    } else {
                        // Mover archivo sin comprimir
                        $ruta .= '.' . $file_ext;
                        if (!move_uploaded_file($file['tmp_name'], $ruta)) {
                            $error = "Error al mover el archivo.";
                        }
                    }
                }
            }
        }

        // ACTUALIZACIÓN EN BASE DE DATOS (si no hay errores)        
        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Actualizar documento (diferente consulta si hay nuevo archivo)
                if ($nuevo_archivo) {
                    $stmt = $pdo->prepare("
                         UPDATE documentos 
                        SET nombre = ?, ruta_archivo = ?, tipo = ?, 
                            version = ?, fecha_vencimiento = ?, es_comprimido = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $nombre, $ruta, $tipo, 
                        $version, $fecha_vencimiento, $es_comprimido, 
                        $documento['id']
                    ]);
                } else {
                       $stmt = $pdo->prepare("
                        UPDATE documentos 
                        SET nombre = ?, tipo = ?, version = ?, fecha_vencimiento = ? 
                        WHERE id = ?
                    ");
                       $stmt->execute([
                        $nombre, $tipo, $version, $fecha_vencimiento, 
                        $documento['id']
                    ]);
                }

                // Manejar relación con trámites (opcional)
                $tramite_id = isset($_POST['tramite_id']) && is_numeric($_POST['tramite_id']) 
                    ? (int)$_POST['tramite_id'] 
                    : null;
                
                   $stmt = $pdo->prepare("DELETE FROM documentos_tramites WHERE documento_id = ?");
                   $stmt->execute([$documento['id']]);

                if ($tramite_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documentos_tramites (tramite_id, documento_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$tramite_id, $documento['id']]);
                }

                $pdo->commit();
                
                // Regenerar token CSRF para el próximo formulario 
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header("Location: index.php?id=" . $documento['id']);
                exit;
                
            } catch (PDOException $e) {
                // Revertir transacción en caso de error
                 $pdo->rollBack();
                $error = "Error al actualizar el documento: " . $e->getMessage();
                
                // Eliminar archivo subido si hubo error
                if ($nuevo_archivo && file_exists($ruta)) {
                    unlink($ruta);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $documento ? 'Actualizar Documento' : 'Documento no encontrado' ?></title>
    
    <link  rel="stylesheet" href="css/bootstrap.min.css">
     <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->
    <style>
        /* Estilos idénticos a cargar_documento.php */
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
            background-color:  #212529;
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

        .btn-custom {
            border-radius: 10px;
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
            border-color: #11953c;
            color: white;
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            background-color: #5a6268;
            border-color: #5a6268;
            color: white;
        }

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

        .bienvenida h3 {
            margin: 0;
            color: #343a40;
        }

        .navg {
            font-weight: 600;
        }

        .alert {
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

            .btn-custom, .btn-secondary {
                min-width: 80px;
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .form-control, .form-select {
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
    <?php if ($documento && isset($documento['nombre'])): ?>
        <div class="bienvenida text-center mb-4">
            <h3>Actualizar Documento: <?= htmlspecialchars($documento['nombre']) ?></h3>
        </div>

        <!-- Muestra errores si los hay -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Botones de navegación -->
        <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
            <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
            <?php if ($es_admin): ?>
                <a href="admin.php" class="btn btn-custom">Panel de Administración</a>
            <?php endif; ?>
        </div>

        <!-- Formulario para actualizar documento -->
        <form method="POST" enctype="multipart/form-data" class="row g-3" id="formActualizarDocumento">
            <!-- Campo CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
            
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre del documento</label>
                <input type="text" name="nombre" id="nombre" class="form-control" 
                       value="<?= htmlspecialchars($documento['nombre']) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="tipo" class="form-label">Descripción</label>
                <input type="text" name="tipo" id="tipo" class="form-control" 
                       value="<?= htmlspecialchars($documento['tipo']) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="version" class="form-label">Versión</label>
                <select name="version" id="version" class="form-select" required>
                    <option value="copia" <?= $documento['version'] == 'copia' ? 'selected' : '' ?>>Copia</option>
                    <option value="certificada" <?= $documento['version'] == 'certificada' ? 'selected' : '' ?>>Certificada</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" 
                       value="<?= ($documento['fecha_vencimiento']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Área</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($documento['area_nombre']) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label for="tramite_id" class="form-label">Trámite (opcional)</label>
                <select name="tramite_id" id="tramite_id" class="form-select">
                    <option value="">Sin trámite</option>
                    <?php foreach ($tramites as $tramite): ?>
                        <option value="<?= htmlspecialchars($tramite['id']) ?>" 
                            <?= $tramite['id'] == $documento['tramite_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tramite['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label for="archivo" class="form-label">Nuevo archivo (opcional, PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, máx. 125MB)</label>
                <input type="file" name="archivo" id="archivo" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                <div class="form-text">Archivo actual: <?= basename($documento['ruta_archivo']) ?> (<?= $documento['es_comprimido'] ? 'Comprimido' : 'Sin comprimir' ?>)</div>
                <div id="errorArchivo" class="invalid-feedback"></div>
            </div>
            <div class="col-12 text-center">
                <button type="submit" name="actualizar_documento" class="btn btn-custom">Actualizar Documento</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-danger text-center">
            <?= isset($error) ? htmlspecialchars($error) : 'Documento no encontrado o no tienes permisos para editarlo.' ?>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
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

        const form = document.getElementById('formActualizarDocumento');
        const archivoInput = document.getElementById('archivo');
        const errorArchivo = document.getElementById('errorArchivo');
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        const maxSize = 125 * 1024 * 1024; 

        archivoInput.addEventListener('change', () => {
            errorArchivo.textContent = '';
            archivoInput.classList.remove('is-invalid');

            const file = archivoInput.files[0];
            if (!file) return; 

            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExt)) {
                errorArchivo.textContent = 'Extensión no permitida. Solo se permiten: ' + allowedExtensions.join(', ') + '.';
                archivoInput.classList.add('is-invalid');
                return;
            }

            if (file.size > maxSize) {
                errorArchivo.textContent = 'El archivo excede el tamaño máximo permitido (125 MB).';
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