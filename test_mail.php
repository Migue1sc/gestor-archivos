<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'algun_correo@gmail.com';//correo emisor de notificaciones 
    $mail->Password = 'abcd efgh ijkl mnop'; //no es contraseña de correo, es una contraseña de aplicacion
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('algun_correo@gmail.com', 'Prueba');//tambien correo emisor de notificaciones
    $mail->addAddress('correo_destino@gmail.com'); //usar un correo real
    
    $mail->isHTML(true);
    $mail->Subject = 'Prueba Local';
    $mail->Body = '¡Correo de prueba desde localhost y phpMailer!';
    
    $mail->send();
    echo 'Correo enviado';
} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
?>