<?php
session_start();
include 'includes/config.php';//se incluye archivo de conexión a la base 

if (!isset($_SESSION['user_id'])){//se verifica que la sesión sea correcta 
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_admin = $_SESSION['es_admin'] ?? 0;
//solo administradores pueden ver todas las áreas
//if ($_SESSION['es_admin'] != 1) {
    //header("Location: index.php");
    //exit;
//}

//aquuí se consultan todas las áreas
$areas = $pdo->query("
    SELECT a1.id, a1.nombre, a1.area_padre_id, a2.nombre AS padre 
    FROM areas a1 
    LEFT JOIN areas a2 ON a1.area_padre_id = a2.id
    ORDER BY a1.area_padre_id IS NULL DESC, a1.nombre
")->fetchAll();

//esta parte organiza las áreas
$areas_organizadas =[];
foreach ($areas as $area){//se recorren las áreas existentes 
    if ($area['area_padre_id'] === null){
        $areas_organizadas[$area['id']] = [//se organizan las áreas principalmente por id
            'id' => $area['id'],
            'nombre' => $area['nombre'],
            'subareas' => []
        ];
    } else{
        $areas_organizadas[$area['area_padre_id']]['subareas'][] =[
            'id' => $area['id'],
             'nombre' => $area['nombre']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Áreas</title>
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
            transition: all 0.3 ease;
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
    filter: invert(100%);
        
}

        .container{
            max-width:1100px;
            width:100%;
            margin:80px 20px 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter:blur(30px);
            animation:slideIn 0.8s ease-out;
            padding:2.5rem;
            min-height: 600px;
        }

        .card {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        
        }

        .card:hover{
            transform:translateY(-5px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background: rgba(255, 255, 255, 0.5);
        }

        .card-link{
            text-decoration: none;
            color:#333;
        }

        .card-link:hover {
            color: #11953c;
        }

        .card-title {
            color:#000;
            font-weight:bold;
        }

        .card-text {
            color:#333;
            font-size:1.1rem;
            font-weight: 600;
        }

        .subarea-card{
            background: rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        
        }

        .subarea-card:hover{
            background: rgba(255, 255, 255, 0.6);
            transform:translateX(5px);
        }

        .btn-custom-sub {
            border-radius:5px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
            background-color: #79428d;
            border-color: #79428d;
            color: white;
            min-width: 90px;
            font-size: 0.90rem;
            text-align: center;
        }

        .btn-custom-sub:hover{
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color: #11953c;
            color: white;
        }
        
        @keyframes slideIn{
            from{
                opacity:0;
                
                transform:translateY(30px);
            }
            to{
                opacity:1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px){
            .container{
                margin:60px 10px 10px;
                
                padding:1.5rem;
                min-height: 400px;
            }

            .card-text{
                font-size:1rem;
            }
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
    <a class="logout-btn d-flex align-items-center justify-content-center" href="logout.php">
    <img src="icons/box-arrow-right.svg" alt="Repetir" class="logout me-1">Cerrar Sesión
    </a>
</div>
    <div class="container">
        <h1 class="text-center mb-4">Lista de Áreas</h1>

        <!--botones-->
        <div class="text-center mb-4">
            <?php if ($_SESSION['es_admin']):?><!--verifica que sea una sesión de administradior para poder avanzar al panel de administrador-->
                 <a href="admin.php" class="btn btn-custom-sub">Panel de Administración</a>
            <?php endif;?>
        </div>

        <!--grrid de áreas-->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 justify-content-center">
            <?php if (count($areas_organizadas) > 0):?><!--se verifica si hay áreas antes de mostrar el html-->
                <?php foreach ($areas_organizadas as $area):?><!--recorre el arreglo para mostrar las áreas disponibles-->
                    <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                                <a href="documentos_area.php?area_id=<?= htmlspecialchars($area['id']) ?>" class="card-link">
                                    <h5 class="card-title"><?= htmlspecialchars($area['nombre'])?></h5>
                                    <p class="card-text">Haz clic para ver los documentos.</p>
                                </a>
                                 <?php if (!empty($area['subareas'])):?><!--si la variable esta vacia devuelve true y lista áreas principales que no tienen subáreas-->
                                    <hr class="my-3">
                                    <h6 class="text-muted">Subáreas</h6>
                                        <?php foreach ($area['subareas'] as $subarea):?><!--recorre el arreglo subáreas guardando el dato en subáreas para mostrarlo-->
                                        <a href="documentos_area.php?area_id=<?= htmlspecialchars($subarea['id'])?>" class="card-link">
                                            <div class="subarea-card">
                                                <p class="card-text mb-0"><?= htmlspecialchars($subarea['nombre'])?></p>
                                            </div>
                                        </a>
                                        <?php endforeach;?>
                                    <?php endif;?>
                            </div>
                     </div>
                    </div>
                <?php endforeach;?>
              <?php else: ?> <!--si no hay áreas se muestra el mensaje del párrafo-->
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <p class="card-text">No hay áreas disponibles.</p>
                        </div>
                    </div>
                </div>
              <?php endif;?>
        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>

    <!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->
    
</body>
</html>