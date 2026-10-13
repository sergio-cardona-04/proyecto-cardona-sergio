<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

// Cargar las dependencias de Composer
require_once '/opt/incidencias/vendor/autoload.php';

/**
 * Enviar un correo cuando se registre una incidencia
 */
function enviarCorreoIncidencia(array $incidencia): void
{
    $correo = new PHPMailer(true);

    // 1. Configurar SMTP
    $correo->isSMTP();

    $correo->Host = entorno('SMTP_HOST');
    $correo->Port = (int) entorno('SMTP_PORT');

    // Mailpit no requiere autenticación ni TLS
    $correo->SMTPAuth = false;
    $correo->SMTPAutoTLS = false;

    $correo->Timeout = 5;
    $correo->CharSet = 'UTF-8';

    // 2. Configurar remitente y destinatario
    $correo->setFrom(
        entorno('MAIL_FROM'),
        'Sistema de Incidencias'
    );

    $correo->addAddress(
        entorno('MAIL_TO')
    );

    // 3. Asunto del correo
    $correo->Subject =
        'Nueva incidencia registrada #' . $incidencia['id'];

    // 4. Preparar contenido del mensaje
    $correo->isHTML(false);

    $correo->Body = implode("\n", [
        'Se ha registrado una nueva incidencia.',
        '',
        'ID: ' . $incidencia['id'],
        'Título: ' . $incidencia['titulo'],
        'Descripción: ' . $incidencia['descripcion'],
        'Categoría: ' . $incidencia['categoria'],
        'Estado: ' . $incidencia['estado'],
        'Fecha: ' . $incidencia['fecha_creacion'],
        '',
        'Este es un mensaje de prueba generado por el sistema.'
    ]);

    // 5. Enviar el mensaje
    $correo->send();
}
