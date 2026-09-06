<?php
/**
 * Subida y borrado de adjuntos de una entrega (petición asíncrona).
 *
 * Devuelve siempre JSON. El archivo se valida aquí —extensión, tamaño, dueño
 * de la entrega— antes de llegar al almacenamiento.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/adjuntos.php';

header('X-Content-Type-Options: nosniff');

$u = usuario();
if (!$u)                          json_salida(['ok' => false, 'error' => 'Tu sesión caducó. Vuelve a entrar.'], 401);
if (!es_post() || !csrf_valido()) json_salida(['ok' => false, 'error' => 'Token inválido; recarga la página.'], 419);

$accion    = post('accion') ?: 'subir';
$entregaId = post_int('entrega_id');

$e = fila('SELECT e.*, a.docente_id, a.estado AS estado_asignacion, a.actividad_id
             FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
            WHERE e.id = ?', [$entregaId]);
if (!$e) json_salida(['ok' => false, 'error' => 'No encontramos esa entrega.'], 404);

// Solo el dueño de la entrega, su docente y la administración.
$suya = (int) $u['id'] === (int) $e['estudiante_id'];
$suyo = es('docente') && (int) $u['id'] === (int) $e['docente_id'];
if (!$suya && !$suyo && !es('admin')) json_salida(['ok' => false, 'error' => 'No tienes acceso a esa entrega.'], 403);

$bloqueada = $e['estado'] === 'revisada' || $e['estado_asignacion'] === 'cerrada';

// ------------------------------------------------------------------ borrar --
if ($accion === 'borrar') {
    $adj = fila('SELECT * FROM entrega_adjuntos WHERE id = ? AND entrega_id = ?', [post_int('id'), $entregaId]);
    if (!$adj) json_salida(['ok' => false, 'error' => 'Ese archivo ya no está.'], 404);
    if (!adjunto_borrable_por($adj, $u, $bloqueada && $suya)) {
        json_salida(['ok' => false, 'error' => 'No puedes quitar ese archivo.'], 403);
    }
    adjunto_borrar($adj);
    json_salida(['ok' => true]);
}

// ------------------------------------------------------------------- subir --
if ($bloqueada && $suya) {
    json_salida(['ok' => false, 'error' => 'La actividad ya fue calificada: no admite archivos nuevos.'], 403);
}
if (empty($_FILES['archivo'])) json_salida(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 400);

// La fase, si se indicó, tiene que ser de esta actividad.
$faseId = post_int('fase_id') ?: null;
if ($faseId && !valor('SELECT id FROM actividad_fases WHERE id = ? AND actividad_id = ?', [$faseId, $e['actividad_id']])) {
    $faseId = null;
}

[$ok, $res] = adjunto_guardar($_FILES['archivo'], $entregaId, $faseId, (int) $u['id']);
if (!$ok) json_salida(['ok' => false, 'error' => $res], 422);

json_salida([
    'ok'      => true,
    'adjunto' => [
        'id'        => (int) $res['id'],
        'nombre'    => $res['nombre'],
        'extension' => $res['extension'],
        'etiqueta'  => adjunto_etiqueta((string) $res['extension']),
        'peso'      => adjunto_peso((int) $res['bytes']),
        'url'       => url('portal/adjunto.php?id=' . (int) $res['id']),
        'borrable'  => true,
    ],
]);
