<?php
/**
 * Configuración y utilidades de la pasarela de pagos (Mercado Pago).
 *
 * Aquí viven la configuración, la detección de entorno y la verificación de la
 * firma del webhook. El cobro propiamente dicho está en pagos_api.php.
 *
 * Las credenciales nunca se escriben en el código: llegan de
 * includes/config.local.php, de variables de entorno o de los ajustes.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Lee una credencial: constante → variable de entorno → ajustes del portal. */
function mp_credencial(string $constante, string $entorno, string $clave): string {
    if (defined($constante) && constant($constante) !== '') return (string) constant($constante);
    $env = getenv($entorno);
    if ($env) return $env;
    return (string) ajuste($clave, '');
}

function mp_access_token(): string   { return mp_credencial('MERCADOPAGO_ACCESS_TOKEN', 'VCP_MP_ACCESS_TOKEN', 'mp_access_token'); }
function mp_public_key(): string     { return mp_credencial('MERCADOPAGO_PUBLIC_KEY', 'VCP_MP_PUBLIC_KEY', 'mp_public_key'); }
function mp_webhook_secret(): string { return mp_credencial('MERCADOPAGO_WEBHOOK_SECRET', 'VCP_MP_WEBHOOK_SECRET', 'mp_webhook_secret'); }

/**
 * Entorno de la pasarela. No se deduce del token: Mercado Pago entrega
 * credenciales de prueba que también empiezan por APP_USR-, así que el modo lo
 * declara el administrador en Ajustes (o la constante MERCADOPAGO_MODO).
 */
function mp_entorno(): string {
    if (mp_access_token() === '') return 'desconocido';
    if (defined('MERCADOPAGO_MODO')) {
        return MERCADOPAGO_MODO === 'prueba' ? 'prueba' : 'produccion';
    }
    $env = getenv('VCP_MP_MODO');
    if ($env) return $env === 'prueba' ? 'prueba' : 'produccion';
    // Los tokens con prefijo TEST- son inequívocamente de prueba.
    if (str_starts_with(mp_access_token(), 'TEST-')) return 'prueba';
    return ajuste('mp_modo', 'prueba') === 'produccion' ? 'produccion' : 'prueba';
}

function mp_configurado(): bool { return mp_access_token() !== ''; }

/** Últimos caracteres de una credencial, para mostrarla sin exponerla. */
function mp_pista(string $valor): string {
    return $valor === '' ? '' : '…' . substr($valor, -6);
}

/** URL pública que hay que registrar en Mercado Pago como webhook. */
function mp_url_webhook(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'www.vcodepro.de';
    $esquema = esquema_publico();
    return $esquema . '://' . $host . url('portal/api/mercadopago.php');
}

/**
 * Valida la firma de una notificación de Mercado Pago.
 *
 * Mercado Pago envía la cabecera x-signature con la forma
 *   ts=1704908010,v1=<hmac>
 * y el HMAC-SHA256 se calcula sobre el manifiesto
 *   id:<data.id>;request-id:<x-request-id>;ts:<ts>;
 * usando la clave secreta que genera el panel al registrar la URL.
 *
 * Devuelve 'valida', 'invalida' o 'sin_secreto'.
 */
function mp_verificar_firma(array $cabeceras, string $recursoId): string {
    $secreto = mp_webhook_secret();
    if ($secreto === '') return 'sin_secreto';

    $firma = $cabeceras['x-signature'] ?? '';
    $peticion = $cabeceras['x-request-id'] ?? '';
    if ($firma === '' || $recursoId === '') return 'invalida';

    $ts = ''; $v1 = '';
    foreach (explode(',', $firma) as $parte) {
        [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($k === 'ts') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    if ($ts === '' || $v1 === '') return 'invalida';

    // El identificador del recurso va en minúsculas cuando es alfanumérico.
    $id = ctype_digit($recursoId) ? $recursoId : mb_strtolower($recursoId);
    $manifiesto = "id:$id;request-id:$peticion;ts:$ts;";
    $calculada = hash_hmac('sha256', $manifiesto, $secreto);

    return hash_equals($calculada, $v1) ? 'valida' : 'invalida';
}

/** Cabeceras de la petición en minúsculas, para leerlas sin sorpresas. */
function cabeceras_peticion(): array {
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $out[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
        }
    }
    return $out;
}

/** Guarda una notificación recibida. Devuelve el id del registro. */
function mp_registrar_notificacion(array $d): int {
    return insertar('pagos_webhook', [
        'origen'     => 'mercadopago',
        'entorno'    => $d['entorno'] ?? 'desconocido',
        'tipo'       => $d['tipo'] ?? null,
        'accion'     => $d['accion'] ?? null,
        'recurso_id' => $d['recurso_id'] ?? null,
        'firma'      => $d['firma'] ?? 'sin_secreto',
        'cuerpo'     => mb_substr((string) ($d['cuerpo'] ?? ''), 0, 60000),
        'cabeceras'  => mb_substr((string) ($d['cabeceras'] ?? ''), 0, 4000),
        'ip'         => $d['ip'] ?? null,
        'nota'       => $d['nota'] ?? null,
    ]);
}
