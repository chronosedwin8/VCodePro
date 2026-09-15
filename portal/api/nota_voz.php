<?php
/**
 * Recepción de una nota de voz grabada en el navegador (petición asíncrona).
 *
 * El navegador la entrega ya optimizada —mono, Opus a 24 kbps—. Aquí no se
 * confía en lo que diga el navegador sobre el archivo: se comprueba por sus
 * primeros bytes que de verdad es audio, que no pasa del tamaño que
 * corresponde a cinco minutos, y se guarda en S3 como un adjunto más.
 *
 * Pueden grabar el dueño de la entrega y su docente —una retroalimentación
 * hablada también es útil—, con los mismos permisos que el resto de adjuntos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/adjuntos.php';

header('X-Content-Type-Options: nosniff');

$u = usuario();
if (!$u)                          json_salida(['ok' => false, 'error' => 'Tu sesión caducó. Vuelve a entrar.'], 401);
if (!es_post() || !csrf_valido()) json_salida(['ok' => false, 'error' => 'Token inválido; recarga la página.'], 419);

$entregaId = post_int('entrega_id');
$e = fila('SELECT e.*, a.docente_id, a.estado AS estado_asignacion, a.actividad_id
             FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
            WHERE e.id = ?', [$entregaId]);
if (!$e) json_salida(['ok' => false, 'error' => 'No encontramos esa entrega.'], 404);

$suya = (int) $u['id'] === (int) $e['estudiante_id'];
$suyo = es('docente') && (int) $u['id'] === (int) $e['docente_id'];
if (!$suya && !$suyo && !es('admin')) json_salida(['ok' => false, 'error' => 'No tienes acceso a esa entrega.'], 403);

$bloqueada = $e['estado'] === 'revisada' || $e['estado_asignacion'] === 'cerrada';
if ($bloqueada && $suya) {
    json_salida(['ok' => false, 'error' => 'La actividad ya fue calificada: no admite notas nuevas.'], 403);
}
if (empty($_FILES['audio'])) json_salida(['ok' => false, 'error' => 'No llegó ninguna grabación.'], 400);

// La fase, si se indicó, tiene que ser de esta actividad.
$faseId = post_int('fase_id') ?: null;
if ($faseId && !valor('SELECT id FROM actividad_fases WHERE id = ? AND actividad_id = ?', [$faseId, $e['actividad_id']])) {
    $faseId = null;
}

[$ok, $res] = nota_voz_guardar($_FILES['audio'], $entregaId, $faseId, (int) $u['id'], post_int('duracion'));
if (!$ok) json_salida(['ok' => false, 'error' => $res], 422);

json_salida(['ok' => true, 'adjunto' => adjunto_json($res)]);
