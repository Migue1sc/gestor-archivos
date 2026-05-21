<?php
//se incluye el archivo de conexión a la BD
include 'includes/config.php'; 
//este archivo es para envianr correos, lo vi en tutorial de phpmailer
include 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//esta función es para enviar correos
function enviarMail($pdo, $id_notif, $email_destino, $asunto_correo, $contenido) {
    //se crea el objeto de php mailer
    $mail = new PHPMailer(true); //se hace true para las excepciones
    try{
        //configuracion del servidor smtp, parece que gmail es más facil
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'correo_emisor@gmail.com'; //mi correo para pruebas
        $mail->Password = 'abcd efgh ijkl mnop'; //contraseña de app de Gmail
        $mail->SMTPSecure = 'tls'; //esto lo vi en un foro y es para cifras la comunicacion 
        $mail->Port = 587;

        //se pone el correo de quién envía y a quién va el correo
        $mail->setFrom('correo_emisor@gmail.com', 'Sistema de Documentos');
        $mail->addAddress($email_destino);

        //esto es el cuerpo del correo en html
        $mail->isHTML(true); //estpo para que el correo tenga formato
        $mail->Subject = $asunto_correo;
        $mail->Body = $contenido;

        //se envía el correo
        $mail->send();
        echo "Correo enviado a $email_destino <br>"; //para comprobar qeue se envio el coreo **quitar

        //aquí se acutualiza la base de datos para marcar que se envió
        $sql= "UPDATE notificaciones SET estado = 'enviado', fecha_envio = NOW() WHERE id = ?";
        $query = $pdo->prepare($sql);
        $query->execute([$id_notif]);
        return true;
    } catch (Exception $e) {
        //si sale mal algo , se marca como fallido
        $sql= "UPDATE notificaciones SET estado = 'fallido', fecha_envio = NOW() WHERE id = ?";
        $query = $pdo->prepare($sql);
        $query->execute([$id_notif]);
        //se guarda el error en  un log
        error_log("No se pudo enviar el correo: " . $mail->ErrorInfo);
        return false;
    }
}

//se obttiene las fechas para buscar documentos
$fecha_actual = date('Y-m-d'); //hoy
$fecha_7_dias = date('Y-m-d', strtotime('+7 days')); //dentro de 7 días

//s ehace una consulta para documentos vencidos o que van a vencer pronto
$sql= "SELECT d.*, u.email, u.nombre AS nombre_usuario 
        FROM documentos d 
        JOIN usuarios u ON d.usuario_id = u.id 
        WHERE d.fecha_vencimiento IS NOT NULL 
        AND (d.fecha_vencimiento <= ? OR d.fecha_vencimiento <= ?)";
$query= $pdo->prepare($sql);
$query->execute([$fecha_actual, $fecha_7_dias]); //se psan los parámetros
$documentos = $query->fetchAll();
//se recorre cada documento para enviar notificaciones
foreach ($documentos as $doc) {
    //si está vencido se marca así, sino se marca proximo a vencer
    if ($doc['fecha_vencimiento'] <= $fecha_actual) {
        $estado = 'vencido';
    } else {
        $estado = 'próximo a vencer';
    }

    //se crea  el asunto y el mensaje del correo
    $asunto = "Aviso: Documento $estado - " . $doc['nombre'];
    $mensaje = "<h2>Notificación</h2>"; //cambié a h2, se ve mejor
    $mensaje .= "<p>El documento <b>" . $doc['nombre'] . "</b> está $estado.</p>";
    $mensaje .= "<p>Vence el: " . $doc['fecha_vencimiento'] . "</p>";
    $mensaje .= "<p>Revisa el sistema para más info.</p>";

    //checa si se envió algo parecido en los últimos 7 días

    $sql = "SELECT id FROM notificaciones 
            WHERE documento_id = ? AND tipo = ? AND fecha_envio >= NOW() - INTERVAL 7 DAY";
    $query = $pdo->prepare($sql);
    $query->execute([$doc['id'], $estado]);
    if ($query->fetch()) {
        continue; //no se envia si ya se mandó algo
    }

    //se guarda la notificación en la base
    $sql = "INSERT INTO notificaciones (documento_id, usuario_id, tipo, mensaje, fecha_envio, estado) 
            VALUES (?, ?, ?, ?, NOW(), 'pendiente')";
    $query = $pdo->prepare($sql);
    $query->execute([$doc['id'], $doc['usuario_id'], $estado, $mensaje]);
    $id_nueva_notif = $pdo->lastInsertId();

    //con esto se obtiene el correo del usuario, si no hay se usa unio por defecto
    $correo_usuario = $doc['email'];
    if (empty($correo_usuario)) {
        $correo_usuario = $doc['usuario'] . '@dominio.com'; //fallback
    }

    //se envía el correo
    enviarMail($pdo, $id_nueva_notif, $correo_usuario, $asunto, $mensaje);
}

//se imprime para verificar que salió bien el envio 
echo "se enviaron las notificaciones corretamete !";
?>