<?php
/**
 * Calificación asistida de UNA entrega (petición asíncrona).
 *
 * La página del docente llama aquí una vez por estudiante, en serie, para
 * poder mostrar el avance y decir exactamente cuál falló. Una sola petición
 * que calificara a todo el grupo se pasaría del tiempo máximo de ejecución y
 * dejaría al docente mirando una pantalla en blanco.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/ia_calificar.php';

header('X-Content-Type-Options: nosniff');

$u = usuario();
if (!$u)                          json_salida(['ok' => false, 'error' => 'Tu sesión caducó. Vuelve a entrar.'], 401);
if (!es_post() || !csrf_valido()) json_salida(['ok' => false, 'error' => 'Token inválido; recarga la página.'], 419);
if (!ia_permitida($u))            json_salida(['ok' => false, 'error' => 'No tienes habilitado el asistente de IA.'], 403);

[$ok, $r] = ia_calificar_entrega(post_int('entrega_id'), $u);

if (!$ok) json_salida(['ok' => false, 'error' => $r], 422);
json_salida(['ok' => true, 'resultado' => $r]);
