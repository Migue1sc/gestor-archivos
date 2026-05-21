<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['es_admin'] != 1) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//consulta usuarios y áreas para los formularios
$usuarios = $pdo->query("SELECT id, nombre FROM usuarios")->fetchAll();
$areas = $pdo->query("SELECT id, nombre FROM areas")->fetchAll();

//consulta permisos existentes
$permisos = $pdo->prepare("
    SELECT p.*, u.nombre AS usuario_nombre, a.nombre AS area_nombre 
    FROM permisos_area p 
    JOIN usuarios u ON p.usuario_id = u.id 
    JOIN areas a ON p.area_id = a.id
");
if ($permisos === false) {
    die("Error al preparar la consulta de permisos: " . $pdo->errorInfo()[2]);
}
$permisos->execute();
$permisos_lista = $permisos->fetchAll();

//asigna nuevo permiso
if (isset($_POST['asignar_permiso'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    $area_id = (int)$_POST['area_id'];
    $lectura = isset($_POST['lectura']) ? 1 : 0;
    $escritura = isset($_POST['escritura']) ? 1 : 0;
    $eliminacion = isset($_POST['eliminacion']) ? 1 : 0;
    $gestion = isset($_POST['gestion']) ? 1 : 0;

    //verifica si ya existe un permiso para este usuario y área
    $stmt = $pdo->prepare("SELECT id FROM permisos_area WHERE usuario_id = ? AND area_id = ?");
    $stmt->execute([$usuario_id, $area_id]);
    if ($stmt->fetch()) {
        //actualiza permiso existente
        $stmt = $pdo->prepare("
            UPDATE permisos_area 
            SET lectura = ?, escritura = ?, eliminacion = ?, gestion = ?
            WHERE usuario_id = ? AND area_id = ?
        ");
        $stmt->execute([$lectura, $escritura, $eliminacion, $gestion, $usuario_id, $area_id]);
    } else {
        //inserta nuevo permiso
        $stmt = $pdo->prepare("
            INSERT INTO permisos_area (usuario_id, area_id, lectura, escritura, eliminacion, gestion)
             VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $area_id, $lectura, $escritura, $eliminacion, $gestion]);
    }
    header("Location: permisos_area.php");
    exit;
}

//elimina permiso
if (isset($_GET['eliminar_id'])) {
    $id = (int)$_GET['eliminar_id'];
    $stmt = $pdo->prepare("DELETE FROM permisos_area WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: permisos_area.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Permisos por Área</title>
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
            font-weight: 300; /*estilo para adelgazar la fuente en toda la página*/
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

        /*estilo del botón personalizado*/
        .btn-custom{
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

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            border-color: #11953c;
            color: white;
        }

        .btn-custom-sub{
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

        .btn-custom-sub:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            border-color: #11953c;
            color: white;
        
        }

        .btn-danger{
            border-radius: 5px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            padding: 0.4rem 0.8rem;
         }

        .btn-danger:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        
        }
        
        .logout, .trash{
    filter: invert(100%);
}
        .table {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            overflow: hidden;
        }

        .table-dark {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.4);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(39, 174, 96, 0.2);
        }

        .table td, .table th {
            border: none;
            padding: 1rem;
        }

        .form-control, .form-select{
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
    }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #27ae60;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);

        }

        .form-check-input {
            margin-right: 0.5rem;
        }

        .form-check-label {
            color: #333;
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
        }
    </style>
</head>
<body>
<body>
<div class="topnav">
    <div class="nav-links">
        <a class="navg" href="index.php">Inicio</a>
        <a class="navg" href="tramites.php">Trámites</a>
        <a class="navg" href="areas.php">Áreas</a>
        <?php if ($es_admin): ?>
        <a class="navg active" href="permisos_area.php">Permisos</a>
        <?php endif; ?>
    </div>
    <a class="logout-btn d-flex align-items-center justify-content-center" href="logout.php">
    <img src="icons/box-arrow-right.svg" alt="Repetir" class="logout me-1">Cerrar Sesión
    </a>
</div>
    <div class="container">
        <h1 class="text-center mb-4">Gestionar Permisos por Área</h1>

        <!--botones-->
        <div class="text-center mb-4">
        <a href="admin.php" class="btn btn-custom-sub">Volver al Panel de Administración</a>
        </div>

        <!--formulario para asignar/editar permisos -->
        <h3 class="mb-3">Asignar Nuevo Permiso</h3>
        <form method="POST" class="mb-5">
            <div class="mb-3">
                <label for="usuario_id" class="form-label">Usuario</label>
                <select name="usuario_id" id="usuario_id" class="form-select" required>
                    <option value="">Selecciona un usuario</option>
                    <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?= htmlspecialchars($usuario['id']) ?>"><?= htmlspecialchars($usuario['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="area_id" class="form-label">Área</label>
                <select name="area_id" id="area_id" class="form-select" required>
                    <option value="">Selecciona un área</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?= htmlspecialchars($area['id']) ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Permisos</label>
                <div class="form-check">
                    <input type="checkbox" name="lectura" id="lectura" class="form-check-input">
                    <label for="lectura" class="form-check-label">Lectura</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="escritura" id="escritura" class="form-check-input">
                    <label for="escritura" class="form-check-label">Escritura</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="eliminacion" id="eliminacion" class="form-check-input">
                     <label for="eliminacion" class="form-check-label">Eliminación</label>
                </div>
                <!--<div class="form-check">
                    <input type="checkbox" name="gestion" id="gestion" class="form-check-input">
                    <label for="gestion" class="form-check-label">Gestión</label>
                </div>-->
            </div>

            <button type="submit" name="asignar_permiso" class="btn btn-custom">Asignar/Actualizar Permiso</button>
        </form>

        <!--lista de permisos existentes-->
        <h3 class="mb-3">Permisos Asignados</h3>
        <div class="table-responsive" style="background-color: white; border-radius: 10px;">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Usuario</th>
                        <th>Área</th>
                        <th>Lectura</th>
                        <th>Escritura</th>
                        <th>Eliminación</th>
                        <!--<th>Gestión</th>-->
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($permisos_lista) > 0): ?>
                        <?php foreach ($permisos_lista as $permiso): ?>
                            <tr>
                                <td><?= htmlspecialchars($permiso['usuario_nombre']) ?></td>
                                <td><?= htmlspecialchars($permiso['area_nombre']) ?></td>
                                <td><?= $permiso['lectura'] ? 'Sí' : 'No' ?></td>
                                <td><?= $permiso['escritura'] ? 'Sí' : 'No' ?></td>
                                <td><?= $permiso['eliminacion'] ? 'Sí' : 'No' ?></td>
                                <!--<td><?= $permiso['gestion'] ? 'Sí' : 'No' ?></td>-->
                                <td>
                                    <a href="?eliminar_id=<?= htmlspecialchars($permiso['id']) ?>" onclick="return confirm('¿Seguro que deseas eliminar este permiso?')" class="btn btn-danger btn-sm align-items-center justify-content-center"><img src="icons/trash.svg" alt="Eliminar" class="trash me-1">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay permisos asignados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<script src="js/bootstrap.bundle.min.js"></script>
    <!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
    
</body>
</html>