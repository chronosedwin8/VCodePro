<?php
/**
 * Descarga de un adjunto.
 *
 * El portal nunca publica la dirección del archivo en S3: se comprueba aquí
 * quién pide qué y, si tiene permiso, se le redirige a un enlace firmado que
 * caduca en pocos minutos. Así el enlace no sirve para reenviar el trabajo de
 * un estudiante fuera del portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/adjuntos.php';

$u = exigir_login();
$adj = fila('SELECT * FROM entrega_adjuntos WHERE id = ?', [get_int('id')]);

if (!$adj || !adjunto_visible_para($adj, $u)) {
    flash_err('No encontramos ese archivo o no tienes permiso para verlo.');
    redirigir(panel_de($u['rol']));
}

auditar('adjunto_descargado', 'entregas', (int) $adj['entrega_id'], (string) $adj['nombre']);

if ($adj['almacen'] === 's3') {
    $destino = adjunto_url($adj, 300);
    if ($destino === '') {
        flash_err('El almacenamiento no está disponible en este momento.');
        redirigir(panel_de($u['rol']));
    }
    header('Location: ' . $destino, true, 302);
    exit;
}

// Copia en disco: se sirve desde PHP para no depender de la carpeta pública.
$ruta = RUTA_SUBIDAS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $adj['clave']);
if (!is_readable($ruta)) {
    flash_err('El archivo ya no está en el servidor.');
    redirigir(panel_de($u['rol']));
}
header('Content-Type: ' . $adj['tipo']);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $adj['nombre']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
