<?php
/**
 * Recibe el formulario de contacto del sitio público y lo guarda para el
 * panel de administración. Responde JSON; el sitio funciona igual si falla.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';

header('X-Content-Type-Options: nosniff');

if (!es_post()) json_salida(['ok' => false, 'error' => 'metodo'], 405);

$nombre  = post('nombre');
$email   = mb_strtolower(post('correo') ?: post('email'));
$mensaje = post('mensaje');

if ($nombre === '' || $mensaje === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_salida(['ok' => false, 'error' => 'datos'], 422);
}

// Freno sencillo contra envíos repetidos desde la misma dirección.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$recientes = (int) valor(
    'SELECT COUNT(*) FROM mensajes_contacto WHERE email = ? AND creado_en > DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
    [$email], 0
);
if ($recientes >= 3) json_salida(['ok' => false, 'error' => 'demasiados'], 429);

$asunto = post('motivo') ?: 'Consulta general';
$extra  = post('licencias') !== '' ? "\n\nLicencias estimadas: " . post('licencias') : '';
$cargo  = post('cargo') !== '' ? "\nCargo: " . post('cargo') : '';

try {
    $id = insertar('mensajes_contacto', [
        'nombre'   => mb_substr($nombre, 0, 120),
        'email'    => mb_substr($email, 0, 160),
        'colegio'  => mb_substr(post('institucion'), 0, 160) ?: null,
        'telefono' => mb_substr(post('telefono'), 0, 40) ?: null,
        'asunto'   => mb_substr($asunto, 0, 120),
        'mensaje'  => $mensaje . $cargo . $extra,
    ]);

    foreach (filas('SELECT id FROM usuarios WHERE rol = "admin" AND estado = "activo"') as $a) {
        notificar((int) $a['id'], 'Mensaje del sitio: ' . $asunto,
            $nombre . ' · ' . $email, 'portal/admin/mensajes.php', 'aviso');
    }
    auditar('contacto_recibido', 'mensajes_contacto', $id, $email . ' desde ' . $ip);
} catch (Throwable $e) {
    json_salida([
        'ok'      => false,
        'error'   => 'servidor',
        'detalle' => APP_ENTORNO === 'desarrollo' ? $e->getMessage() : null,
    ], 500);
}

json_salida(['ok' => true, 'mensaje' => 'Recibimos tu mensaje. Te responderemos al correo indicado.']);
