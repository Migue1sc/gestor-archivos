<?php
session_start();
include 'includes/config.php';//se incluye el archivo de conexión a la base 

if (!isset($_SESSION['user_id']) || $_SESSION['es_admin'] != 1) {//aquí se comprueba si se inicio la sesión correctamente
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;

//en esta función se teclean los datos para crear un usuario 
if (isset($_POST['crear_usuario'])) {
    $usuario = $_POST['usuario'];
     $email = $_POST['email'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);//al introducir la contraseña, "password_hash" cifra la contraseña
    $nombre = $_POST['nombre'];
    $area_id = $_POST['area_id'];
    $es_admin = in_array($_POST['es_admin'], ['0', '1']) ? $_POST['es_admin'] : 0; //validar es_admin

    $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, email, contrasena, nombre, area_id, es_admin) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$usuario, $email, $contrasena, $nombre, $area_id, $es_admin]);
}

//aquí se crea un trámite 
if (isset($_POST['crear_tramite'])) {
    $nombre = $_POST['nombre_tramite'];
    $stmt = $pdo->prepare("INSERT INTO tramites (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    $tramite_id = $pdo->lastInsertId();

    //se agregan documentos requeridos
    foreach ($_POST['documentos'] as $doc) {
        if (!empty($doc['nombre'])) {
            $obligatorio = isset($doc['obligatorio']) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO documentos_requeridos (tramite_id, nombre_documento, obligatorio) VALUES (?, ?, ?)");
            $stmt->execute([$tramite_id, $doc['nombre'], $obligatorio]);
        }
    }
}$areas= $pdo->query("SELECT * FROM areas")->fetchAll();//se consultan las areas

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link  rel="stylesheet" href="css/bootstrap.min.css">
    
     <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->
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

        .logout-btn:hover{
            background-color: #c82333;
            color: white !important;
        
        }

        .logout{
            filter: invert(100%)
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
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            background-color: #11953c;
            border-color: #11953c;
            color: white;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #0f8235;
            border-color: #0f8235;
            color: white;
        }

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

        .btn-secondary{
            border-radius: 5px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        
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
        }

        .form-control:focus, .form-select:focus{
            background: rgba(255, 255, 255, 0.9);
            border-color: #27ae60;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.3);
        }

        .form-check-input:checked{
            background-color: #27ae60;
            border-color: #27ae60;
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
    <h1 class="text-center mb-4">Panel de Administración</h1>
    
    <!--botones-->
    <div class="text-center mb-4">
        <a href="permisos_area.php" class="btn btn-custom-sub">Gestionar Permisos de Usuario</a>
    </div>

    <!--aquí el contenedor se dividido en dos partes-->
    <div class="row g-4">
        <!--parte izquierda: crear usuario-->
        <div class="col-md-6">
            <h3 class="text-center mb-4">Crear Usuario</h3>
            <form method="POST" class="row g-3">
                <div class="col-12">
                <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                </div>
                <div class="col-12">
                    <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
                </div>
                <div class="col-12">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                </div>
                <div class="col-12">
                    <input type="password" name="contrasena" class="form-control" placeholder="Contraseña" required>
                </div>
                <div class="col-12">
                    <select name="area_id" class="form-select" required>
                        <option value="" disabled selected>Seleccionar área</option>
                        <?php foreach ($areas as $area): ?> <!--script para seleccionar áreas-->
                            <option value="<?= htmlspecialchars($area['id']) ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <select name="es_admin" class="form-select" required>
                        <option value="" disabled selected>Seleccionar tipo de usuario</option>
                        <option value="0">Usuario</option>
                        <option value="1">Administrador</option>
                    </select>
                </div>
                <div class="col-12 text-center">
                <button type="submit" name="crear_usuario" class="btn btn-custom">Crear Usuario</button>
                </div>
            </form>
        </div>

        <!--parte derecha: crear trámite -->
        <div class="col-md-6">
            <h3 class="text-center mb-4">Crear Trámite</h3>
            <form method="POST" class="row g-3">
                <div class="col-12">
                <input type="text" name="nombre_tramite" class="form-control" placeholder="Nombre del Trámite" required>
                </div>
                <div class="col-12">
                    <h4 class="mb-3">Documentos Requeridos</h4>
                    <div id="docs">
                        <div class="mb-3">
                            <input type="text" name="documentos[0][nombre]" class="form-control" placeholder="Nombre del documento">
                            <div class="form-check mt-2">
                            <input type="checkbox" name="documentos[0][obligatorio]" class="form-check-input" checked>
                                <label class="form-check-label">Obligatorio</label>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addDoc()" class="btn btn-secondary mb-3">Agregar otro documento</button>
                </div>
                <div class="col-12 text-center">
                <button type="submit" name="crear_tramite" class="btn btn-custom mb-3">Crear Trámite</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
<!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
    
<script>
    let docCount = 1;
    function addDoc() {
        const div = document.createElement('div');
        div.className = 'mb-3';
        div.innerHTML = `
            <input type="text" name="documentos[${docCount}][nombre]" class="form-control" placeholder="Nombre del documento">
            <div class="form-check mt-2">
                <input type="checkbox" name="documentos[${docCount}][obligatorio]" class="form-check-input" checked>
                <label class="form-check-label">Obligatorio</label>
            </div>
        `;
        document.getElementById('docs').appendChild(div);
        docCount++;
    }

</script>
</body>
</html>