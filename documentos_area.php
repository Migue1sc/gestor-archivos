<?php
session_start();
require 'includes/config.php';//se incluye conexión a al base de datos 

if (!isset($_SESSION['user_id'])) {//aquí se comprueba sesión 
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//solo administradores pueden eliminar documentos, pero todos pueden ver
// if ($_SESSION['es_admin'] != 1) {
//     header("Location: dashboard.php");
//     exit;
// }

if (!isset($_GET['area_id'])) {
    header("Location: areas.php");
    exit;
}

$area_id = (int)$_GET['area_id'];

//esta parte obtiene nombre del área
$area_stmt = $pdo->prepare("SELECT nombre FROM areas WHERE id = ?");
if ($area_stmt === false) {
    die("Error al preparar la consulta de área: " . $pdo->errorInfo()[2]);
}
$area_stmt->execute([$area_id]);
$area = $area_stmt->fetch();
$area_nombre = $area ? htmlspecialchars($area['nombre']) : 'Área no encontrada';

//btener la página actual desde la URL
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

//contar el total de documentos en el área
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE area_id = ?");
    $count_stmt->execute([$area_id]);
    $total_docs = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error al contar documentos: " . $e->getMessage());
}

//aquí se calcula el número total de páginas
$total_pages = $total_docs > 0 ? ceil($total_docs / $records_per_page) : 1;

//esta es una consulta para obtemer documentos del área
$documentos = $pdo->prepare("
    SELECT d.*, u.nombre AS usuario_nombre 
    FROM documentos d 
    LEFT JOIN usuarios u ON d.usuario_id = u.id 
    WHERE d.area_id = ?
    ORDER BY d.fecha_subida DESC 
    LIMIT ? OFFSET ?
");
if ($documentos === false) {
    die("Error al preparar la consulta de documentos: " . $pdo->errorInfo()[2]);
}
//se vinculan parámetros explícitamente como enteros
$documentos->bindValue(1, $area_id, PDO::PARAM_INT);
$documentos->bindValue(2, $records_per_page, PDO::PARAM_INT);
$documentos->bindValue(3, $offset, PDO::PARAM_INT);
$documentos->execute();
$docs = $documentos->fetchAll();
//solo los admins puden eliminar documentos 
if ($es_admin && isset($_GET['eliminar_id'])) {
    $doc_id = (int)$_GET['eliminar_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM documentos_tramites WHERE documento_id = ?");
        $stmt->execute([$doc_id]);
        $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ? AND area_id = ?");
        $stmt->execute([$doc_id, $area_id]);
         header("Location: documentos_area.php?area_id=$area_id&page=$page");
        exit;
    } catch (PDOException $e) {
        die("Error al eliminar documento: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - <?= $area_nombre ?></title>
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
            font-weight: 300; /*adelgazar la fuente en toda la página */
        }

        .topnav{
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

        .topnav a:hover, .topnav a.active {
            background-color: #11953c;
            color: white;
        }

        .container{
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

        .btn-danger{
            border-radius: 5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #dc3545;
            
            border-color: #dc3545;
            color: white;
            min-width: 90px;
            font-size: 0.85rem;
            text-align: center;
        }

        .btn-danger:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            background-color: #c82333;
            border-color: #c82333;
            color: white;
        }

        .logout, .download, .eye, .trash{
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

        .th-head, .td-text{
            text-align: center;
        }

        .table-striped tbody tr:nth-of-type(odd){
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

        .form-control {
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #11953c;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);
        }

        .acciones{
            display: flex;
            flex-direction: column; /* apilados los botones verticalmente */
            
            align-items: center;
            gap: 0.5rem; /*separación entre botones */
        }

        .sticky-top {
        position: sticky;
        top: 0;
        z-index: 1020;
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
            }

            .acciones {
                gap: 0.4rem;
            }
        }

        /* Estilos para la paginación */
        .pagination{
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .page-link{
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
    </style>
</head>
<body>
<div class="topnav">
    <div class="nav-links">
        <a class="navg" href="index.php">Inicio</a>
        <a class="navg" href="tramites.php">Trámites</a>
        <a class="navg active" href="areas.php">Áreas</a>
        <?php if ($es_admin): ?>
        <a class="navg" href="permisos_area.php">Permisos</a>
        <?php endif; ?>
    </div>
    <a class="logout-btn" href="logout.php">
    <img src="icons/box-arrow-right.svg" alt="Descargar" class="logout justify-content-center me-1">Cerrar Sesión
    </a>
</div>
    <div class="container">
        <h1 class="text-center mb-4">Documentos en <?= $area_nombre ?></h1>
        
        <!--botones-->
        <div class="sticky-top text-center mb-4">
            <a href="areas.php" class="btn btn-secondary">Volver a Lista de Áreas</a>
            <?php if ($es_admin): ?>
            <a href="admin.php" class="btn btn-custom">Panel de Administración</a>
            <?php endif; ?>
        </div>
        <!--bar5ra de busqueda-->
        <div class="mb-4">
            <input type="text" id="buscador" class="form-control" placeholder="Buscar documentos por nombre, tipo, subido por o vencimiento..." autocomplete="off">
        </div>

        <div class="table-responsive mb-5" style="background-color: white; border-radius: 10px;">
            <table class="table table-striped table-hover" id="tablaDocumentos">
                <thead class="table-dark">
                    <tr>
                        <th class="th-head">Nombre</th>
                        <th class="th-head">Descripción</th>
                        <th class="th-head">Versión</th>
                        <th class="th-head">Fecha Subida</th>
                        <th class="th-head">Vencimiento</th>
                        <th class="th-head">Subido por</th>
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
                                <td class="td-text"><?= htmlspecialchars($doc['fecha_vencimiento'] ?: 'N/A') ?></td>
                                <td class="td-text"><?= htmlspecialchars($doc['usuario_nombre']) ?></td>
                                <td class="td-text">
                                    <div class="acciones">
                                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" class="btn btn-custom btn-sm d-flex align-items-center" download><img src="icons/file-earmark-arrow-down.svg" alt="Descargar" class="download justify-content-center me-1">Descargar</a>
                                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn btn-secondary btn-sm "><img src="icons/eye.svg" alt="Vista previa" class="eye justify-content-center me-1">Previa</a>
                                        <?php if ($es_admin): ?>
                                            <a href="?area_id=<?= $area_id ?>&eliminar_id=<?= $doc['id'] ?>&page=<?= $page ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar este documento?')"><img src="icons/trash.svg" alt="Vista previa" class="trash justify-content-center me-1"></i>Eliminar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay documentos en esta área.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Controles de paginación -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Paginación de documentos">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?area_id=<?= $area_id ?>&page=<?= $page - 1 ?>" aria-label="Anterior">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);
                    if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="?area_id=<?= $area_id ?>&page=1">1</a></li>
                        <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?area_id=<?= $area_id ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?area_id=<?= $area_id ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?area_id=<?= $area_id ?>&page=<?= $page + 1 ?>" aria-label="Siguiente">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

        <!--botones-->
        <div class="sticky-top text-center mb-4">
            <a href="areas.php" class="btn btn-secondary">Volver a Lista de Áreas</a>
            <?php if ($es_admin): ?>
                <a href="admin.php" class="btn btn-custom">Panel de Administración</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="js/bootstrap.bundle.min.js"></script>

    <!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            //logiva para el buscador 
            const buscador = document.getElementById('buscador');
            const tabla = document.getElementById('tablaDocumentos');
            const filas = tabla.getElementsByTagName('tr');

            buscador.addEventListener('input', () => {
                const termino = buscador.value.toLowerCase();
                for (let i = 0; i < filas.length; i++) {
                    const celdas = filas[i].getElementsByTagName('td');
                    let coincide = false;
                    //buscar en Nombre (0), Tipo (1), Subido por (5), Vencimiento (4)
                    if (celdas.length > 0) {
                        for (let j of [0, 1, 4, 5]) {
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