<?php
/**
 * Receptor de notificaciones (webhook) de Mercado Pago.
 *
 * URL que hay que registrar en el panel de Mercado Pago:
 *   https://www.vcodepro.de/portal/api/mercadopago.php
 *
 * Qué hace hoy:
 *   1. Acepta la notificación, valida la firma x-signature y la registra.
 *   2. Responde 200 de inmediato: Mercado Pago espera una respuesta 2xx en
 *      menos de 22 segundos y reintenta si no la recibe.
 *
 *   3. Si la notificación es de un pago, lo consulta en la API y concilia la
 *      factura correspondiente (la marca pagada, reactiva su licencia, o la
 *      reabre si hubo devolución o contracargo).
 *
 * Todo lo recibido queda guardado en pagos_webhook para poder auditarlo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/pagos_api.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Una petición GET sirve para comprobar desde el navegador que la URL existe.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    json_salida([
        'ok'       => true,
        'servicio' => 'webhook de Mercado Pago',
        'estado'   => mp_webhook_secret() === '' ? 'sin clave secreta configurada' : 'listo',
        'metodo'   => 'Este extremo espera peticiones POST de Mercado Pago.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_salida(['ok' => false, 'error' => 'metodo_no_permitido'], 405);
}

$cuerpo = (string) file_get_contents('php://input');
$datos  = json_decode($cuerpo, true);
if (!is_array($datos)) $datos = [];

$cabeceras = cabeceras_peticion();

// El identificador del recurso viaja en la query (?data.id=) y en el cuerpo.
$recursoId = (string) ($_GET['data.id'] ?? $_GET['id'] ?? ($datos['data']['id'] ?? $datos['id'] ?? ''));
$tipo   = (string) ($datos['type'] ?? $datos['topic'] ?? ($_GET['type'] ?? $_GET['topic'] ?? ''));
$accion = (string) ($datos['action'] ?? '');

$firma = mp_verificar_firma($cabeceras, $recursoId);

// Solo se guardan las cabeceras que sirven para depurar, nunca todas.
$relevantes = array_intersect_key($cabeceras, array_flip([
    'x-signature', 'x-request-id', 'user-agent', 'content-type',
]));

$id = mp_registrar_notificacion([
    'entorno'    => mp_entorno(),
    'tipo'       => $tipo ?: null,
    'accion'     => $accion ?: null,
    'recurso_id' => $recursoId ?: null,
    'firma'      => $firma,
    'cuerpo'     => $cuerpo,
    'cabeceras'  => json_encode($relevantes, JSON_UNESCAPED_SLASHES),
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
    'nota'       => null,
]);

// Con clave secreta configurada, una firma que no cuadra se rechaza.
if ($firma === 'invalida') {
    auditar('mp_webhook_firma_invalida', 'pagos_webhook', $id, $tipo . ' ' . $recursoId);
    json_salida(['ok' => false, 'error' => 'firma_invalida'], 401);
}

// Las notificaciones de pago se concilian contra la factura. El resto se
// registra y se responde 200: Mercado Pago no debe reintentarlas.
$nota = 'Registrada sin conciliar (tipo no relacionado con pagos).';
if ($recursoId !== '' && in_array($tipo, ['payment', 'order'], true)) {
    [$okC, $mensaje] = conciliar_pago($recursoId);
    $nota = ($okC ? '' : 'Error al conciliar: ') . $mensaje;
    actualizar('pagos_webhook', ['procesado' => $okC ? 1 : 0, 'nota' => mb_substr($nota, 0, 300)],
        'id = :id', ['id' => $id]);
}

json_salida(['ok' => true, 'recibido' => $id, 'nota' => $nota]);
