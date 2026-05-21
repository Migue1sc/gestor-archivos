<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>

    <link  rel="stylesheet" href="css/bootstrap.min.css">

    <!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">-->
    <style>
        body {
            min-height: 100vh;
            background: url('image/background_inap.jpg') center/cover no-repeat fixed;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container{
            max-width: 900px;
            width: 100%;
            margin: 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(30px);
            animation: slideIn 0.8s ease-out;
        }

        .login-form {
            padding: 2.5rem;
            margin: auto;
            margin-left: -10px;  
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }

        .login-image {
            background: url('image/inap1.png') center/contain no-repeat;
            height: 100%;
            margin: auto;
            min-height: 400px;
            transition: transform 0.5s ease;
            margin-right: 10px;
        }

        .login-image:hover
        {
            transform: scale(1.03);
        }

        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #27ae60;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.2);
        }

        .btn-login {
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            background-color: #27ae60;
            border-color: #27ae60;
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
            background-color:  #884ea0 ;
            border-color: #884ea0; 
            color: white;
        }

        .form-label{
            font-weight: 600;
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
            .login-image {
                min-height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container row g-0">
        <div class="col-md-6 login-form">
            <h1 class="text-center mb-4">Iniciar Sesión</h1>
            <?php 
            session_start();
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger mb-3">'.$_SESSION['error'].'</div>';
                unset($_SESSION['error']);
            }
            ?>
            <form action="process_login.php" method="post" id="loginForm"><!--cambio de página-->
                <div class="grupo-formulario mb-3">
                    <label for="usuario" class="form-label">Nombre de Usuario:</label>
                    <input type="text" class="form-control" name="usuario" id="usuario" required>
                </div>
                <div class="grupo-formulario mb-4">
                    <label for="contrasena" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" name="contrasena" id="contrasena" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class=" btn-login">Iniciar Sesión</button>
                </div>
                <!--<p class="text-center mt-3">
                    ¿no tienes una cuenta? <a href="registro.php" class="text-decoration-none">Regístrate aquí</a>
                </p>-->
            </form>
        </div>
        
        <div class="col-md-6 login-image"></div>
    </div>

    <!--<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>-->
    <script src="js/popper.min.js"></script>


    <script src="js/bootstrap.bundle.min.js"></script>
     <!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>-->

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = this.querySelector('.btn-login');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cargando...';
            button.disabled = true;
        });

        document.addEventListener('DOMContentLoaded', () => {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            setTimeout(() => {
                container.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>