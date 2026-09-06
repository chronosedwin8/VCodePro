<?php
/**
 * Guardado automático de una fase del ciclo de diseño (petición asíncrona).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/richtext.php';

header('X-Content-Type-Options: nosniff');

$u = usuario();
if (!$u || $u['rol'] !== 'estudiante') json_salida(['ok' => false, 'error' => 'sin_sesion'], 401);
if (!es_post() || !csrf_valido())     json_salida(['ok' => false, 'error' => 'token'], 419);

$entregaId = post_int('entrega_id');
$faseId    = post_int('fase_id');

$e = fila('SELECT e.*, a.actividad_id, a.estado AS estado_asignacion, a.fecha_entrega
             FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
            WHERE e.id = ? AND e.estudiante_id = ?', [$entregaId, $u['id']]);
if (!$e) json_salida(['ok' => false, 'error' => 'no_encontrada'], 404);

if ($e['estado_asignacion'] === 'cerrada' || in_array($e['estado'], ['revisada'], true)) {
    json_salida(['ok' => false, 'error' => 'cerrada'], 403);
}

$fase = fila('SELECT * FROM actividad_fases WHERE id = ? AND actividad_id = ?', [$faseId, $e['actividad_id']]);
if (!$fase) json_salida(['ok' => false, 'error' => 'fase_invalida'], 400);

// Llega HTML del editor enriquecido: se sanea antes de tocar la base de datos.
$contenido  = isset($_POST['contenido']) ? rico_sanear((string) $_POST['contenido']) : null;
$completada = isset($_POST['completada']) ? ($_POST['completada'] === '1') : null;

guardar_fase($entregaId, $faseId, $contenido, $completada);
$progreso = recalcular_progreso($entregaId);

json_salida([
    'ok'       => true,
    'progreso' => $progreso,
    'hora'     => date('H:i'),
]);
