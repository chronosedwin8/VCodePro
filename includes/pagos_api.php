<?php
/**
 * Cobro con Mercado Pago (Checkout Pro) y lo que el cobro desencadena.
 *
 * El portal NUNCA sirve un formulario de tarjeta: crea una preferencia en el
 * servidor y envía al comprador al dominio de Mercado Pago, que captura y
 * procesa los datos de pago. Esto mantiene la integración en el alcance
 * PCI DSS SAQ A, el más liviano, en vez de SAQ A-EP.
 *
 * Contrato con la API:
 *   POST /checkout/preferences          -> init_point
 *   GET  /v1/payments/{id}              -> conciliación (webhook, retorno)
 *   GET  /v1/payments/search?external_reference=… -> conciliación al volver
 *
 * Garantías que sostiene este archivo y que no hay que recortar:
 *
 * 1. **La verdad del cobro es la API**, nunca la URL de retorno: los
 *    parámetros que Mercado Pago añade a las back_urls son falsificables.
 * 2. **Lo cobrado tiene que ser lo facturado.** Un pago aprobado solo salda la
 *    factura si el monto y la moneda coinciden con los de la factura.
 * 3. **Un pago aprobado no retrocede** por una notificación que llega tarde;
 *    solo una devolución o un contracargo lo revierten.
 * 4. **Lo que se paga es lo que se activa**: una compra activa su licencia con
 *    vigencia desde el día del pago, una renovación la extiende. Lo decide el
 *    tipo de la factura, no el texto de su concepto.
 * 5. **Una sola vía para saldar una factura** (factura_marcar_pagada), tanto
 *    para la pasarela como para el «Marcar pagada» del administrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/pagos.php';
require_once __DIR__ . '/planes.php';

/**
 * Nombre que la preferencia pide mostrar en el extracto de la tarjeta.
 * Mercado Pago documenta un máximo de 13 caracteres. Es constante a propósito:
 * no puede depender de un ajuste que alguien deje vacío o con otro nombre.
 */
const MP_DESCRIPTOR = 'VCODEPRO';

/**
 * Llama a la API de Mercado Pago. Devuelve [ok, datos|mensaje, codigoHttp].
 * $idempotencia evita duplicados si la misma petición se reintenta.
 */
function mp_api(string $metodo, string $ruta, ?array $cuerpo = null, ?string $idempotencia = null): array {
    // Solo las pruebas automáticas definen esta función. En el portal no existe.
    if (function_exists('mp_api_simulada')) {
        return mp_api_simulada($metodo, $ruta, $cuerpo, $idempotencia);
    }

    if (mp_access_token() === '') {
        return [false, 'La pasarela de pagos no está configurada.', 401];
    }
    $cabeceras = [
        'Authorization: Bearer ' . mp_access_token(),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($idempotencia !== null) $cabeceras[] = 'X-Idempotency-Key: ' . $idempotencia;

    $ch = curl_init('https://api.mercadopago.com' . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $cabeceras,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }
    $resp   = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return [false, 'No se pudo conectar con Mercado Pago: ' . $err, 0];

    $datos = json_decode((string) $resp, true);
    if (!is_array($datos)) return [false, 'Respuesta ilegible de Mercado Pago.', $codigo];

    if ($codigo >= 400) {
        $msg = $datos['message'] ?? ($datos['error'] ?? ('Error HTTP ' . $codigo));
        return [false, (string) $msg, $codigo];
    }
    return [true, $datos, $codigo];
}

/** Traduce el estado de Mercado Pago al vocabulario del portal. */
function mp_estado(string $status): string {
    return match ($status) {
        'approved', 'authorized' => 'aprobado',
        'in_process', 'pending'  => 'en_proceso',
        'rejected'               => 'rechazado',
        'refunded'               => 'devuelto',
        'cancelled'              => 'cancelado',
        'charged_back'           => 'contracargo',
        default                  => 'en_proceso',
    };
}

/** Mensaje entendible según el motivo que devuelve la pasarela o el portal. */
function mp_motivo(string $detalle): string {
    return match ($detalle) {
        'accredited'                           => 'Pago aprobado y acreditado.',
        'cc_rejected_insufficient_amount'      => 'La tarjeta no tiene fondos suficientes.',
        'cc_rejected_bad_filled_card_number'   => 'Revisa el número de la tarjeta.',
        'cc_rejected_bad_filled_date'          => 'Revisa la fecha de vencimiento.',
        'cc_rejected_bad_filled_security_code' => 'Revisa el código de seguridad.',
        'cc_rejected_bad_filled_other'         => 'Revisa los datos de la tarjeta.',
        'cc_rejected_high_risk'                => 'El pago fue rechazado por seguridad. Prueba con otro medio de pago.',
        'cc_rejected_call_for_authorize'       => 'Autoriza el pago con tu banco y vuelve a intentarlo.',
        'cc_rejected_card_disabled'            => 'La tarjeta está inactiva. Llama a tu banco para activarla.',
        'cc_rejected_duplicated_payment'       => 'Ya existe un pago por ese valor. Espera unos minutos antes de reintentar.',
        'cc_rejected_max_attempts'             => 'Se alcanzó el máximo de intentos. Prueba con otra tarjeta.',
        'cc_rejected_invalid_installments'     => 'La tarjeta no admite ese número de cuotas.',
        'pending_contingency',
        'pending_review_manual'                => 'El pago quedó en revisión. Te avisamos cuando se acredite.',
        'monto_no_coincide', 'moneda_no_coincide', 'pago_de_prueba'
                                               => 'Recibimos el pago y lo estamos verificando.',
        default                                => 'El pago no pudo completarse.',
    };
}

/** Referencia propia que viaja como external_reference y permite conciliar. */
function pago_referencia(int $facturaId): string {
    return 'VCP-F' . $facturaId . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/** URL de retorno del portal tras pasar por Mercado Pago. */
function mp_url_retorno(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'www.vcodepro.de';
    return esquema_publico() . '://' . $host . url('portal/cliente/pago_retorno.php');
}

/** El peso colombiano se cobra sin centavos. */
function mp_monto(float $monto, string $moneda): int|float {
    return strtoupper($moneda) === 'COP' ? (int) round($monto) : round($monto, 2);
}

/** Fecha en el formato que documenta Mercado Pago: 2026-09-15T23:59:59.000-05:00. */
function mp_fecha(int $marca): string {
    return date('Y-m-d\TH:i:s.vP', $marca);
}

/**
 * Crea la preferencia de pago de una factura y devuelve la URL de Mercado Pago
 * a la que hay que enviar al comprador.
 *
 * Devuelve [ok, urlOMensaje, idPagoLocal].
 */
function crear_preferencia(array $factura, array $cliente): array {
    $referencia = pago_referencia((int) $factura['id']);
    $moneda = strtoupper((string) $factura['moneda']);
    $monto  = mp_monto((float) $factura['monto'], $moneda);

    $pagoLocal = insertar('pagos', [
        'factura_id' => (int) $factura['id'],
        'cliente_id' => (int) $cliente['id'] ?: null,
        'referencia' => $referencia,
        'monto'      => $monto,
        'moneda'     => $moneda,
        'estado'     => 'pendiente',
        'entorno'    => mp_entorno(),
    ]);

    // La preferencia deja de aceptar pagos cuando vence la factura, pero nunca
    // antes de tres días: un pago en efectivo necesita tiempo para llegar al
    // punto de pago.
    $vence = strtotime((string) $factura['vence_en'] . ' 23:59:59') ?: 0;
    $hasta = max($vence, time() + 3 * 86400);

    $retorno = mp_url_retorno() . '?ref=' . rawurlencode($referencia);
    $cuerpo = [
        'items' => [[
            'id'          => (string) $factura['numero'],
            'title'       => mb_substr((string) $factura['concepto'], 0, 250),
            'description' => 'VCodePro · factura ' . $factura['numero'],
            'quantity'    => 1,
            'currency_id' => $moneda,
            'unit_price'  => $monto,
        ]],
        'payer' => [
            'name'    => (string) ($cliente['nombre'] ?? ''),
            'surname' => (string) ($cliente['apellidos'] ?? ''),
            'email'   => (string) ($cliente['email'] ?? ''),
        ],
        'back_urls' => ['success' => $retorno, 'pending' => $retorno, 'failure' => $retorno],
        'external_reference'   => $referencia,
        'statement_descriptor' => MP_DESCRIPTOR,
        'expires'              => true,
        'expiration_date_from' => mp_fecha(time() - 60),
        'expiration_date_to'   => mp_fecha($hasta),
        'metadata'             => [
            'factura' => (string) $factura['numero'],
            'tipo'    => (string) ($factura['tipo'] ?? 'manual'),
        ],
    ];

    // auto_return y la notificación exigen direcciones públicas en https; en
    // local se omiten para que la preferencia no sea rechazada.
    if (esquema_publico() === 'https') {
        $cuerpo['auto_return'] = 'approved';
        $cuerpo['notification_url'] = mp_url_webhook();
    }

    [$ok, $resp] = mp_api('POST', '/checkout/preferences', $cuerpo, $referencia);

    if (!$ok) {
        actualizar('pagos', [
            'estado'         => 'rechazado',
            'estado_detalle' => mb_substr((string) $resp, 0, 80),
            'respuesta'      => (string) $resp,
        ], 'id = :id', ['id' => $pagoLocal]);
        auditar('preferencia_error', 'pagos', $pagoLocal, (string) $resp);
        return [false, (string) $resp, $pagoLocal];
    }

    $destino = mp_entorno() === 'produccion'
        ? (string) ($resp['init_point'] ?? '')
        : (string) ($resp['sandbox_init_point'] ?? $resp['init_point'] ?? '');

    if ($destino === '') {
        auditar('preferencia_sin_url', 'pagos', $pagoLocal);
        return [false, 'Mercado Pago no devolvió la dirección de pago.', $pagoLocal];
    }

    actualizar('pagos', [
        'preferencia_id' => mb_substr((string) ($resp['id'] ?? ''), 0, 60),
        'respuesta'      => mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE) ?: '', 0, 60000),
    ], 'id = :id', ['id' => $pagoLocal]);

    auditar('preferencia_creada', 'pagos', $pagoLocal, (string) $factura['numero']);
    return [true, $destino, $pagoLocal];
}

/**
 * Sincroniza un cobro a partir de su referencia propia. Se usa al volver del
 * checkout, donde no se puede confiar en los parámetros de la URL.
 */
function conciliar_por_referencia(string $referencia): array {
    [$ok, $resp, $http] = mp_api('GET', '/v1/payments/search?external_reference=' . rawurlencode($referencia));
    if (!$ok) return [false, (string) $resp, $http === 0 || $http >= 500];

    $resultados = $resp['results'] ?? [];
    if (!$resultados) return [true, 'Todavía no hay ningún pago registrado para esta factura.', false];

    // Si hubo un intento rechazado y luego uno aprobado, manda el aprobado,
    // sin importar cuál sea más reciente.
    $aprobados = array_values(array_filter($resultados, fn($r) => ($r['status'] ?? '') === 'approved'));
    if ($aprobados) {
        $elegido = $aprobados[0];
    } else {
        usort($resultados, fn($a, $b) => strcmp((string) ($b['date_created'] ?? ''), (string) ($a['date_created'] ?? '')));
        $elegido = $resultados[0];
    }
    return conciliar_pago((string) ($elegido['id'] ?? ''));
}

/**
 * Motivo por el que un pago aprobado NO debe saldar la factura, o null.
 * Lo cobrado tiene que ser exactamente lo facturado.
 */
function pago_problema(array $p, array $resp): ?string {
    $cobrado = round((float) ($resp['transaction_amount'] ?? 0), 2);
    if (abs($cobrado - (float) $p['monto']) >= 1) return 'monto_no_coincide';
    if (strcasecmp((string) ($resp['currency_id'] ?? ''), (string) $p['moneda']) !== 0) return 'moneda_no_coincide';
    // Con credenciales de producción, un pago hecho en modo de prueba no salda nada.
    if (mp_entorno() === 'produccion' && array_key_exists('live_mode', $resp) && $resp['live_mode'] === false) {
        return 'pago_de_prueba';
    }
    return null;
}

/**
 * Consulta un pago en Mercado Pago y sincroniza el estado local.
 *
 * Devuelve [ok, mensaje, reintentar]. «reintentar» es true cuando el fallo es
 * pasajero (red, error 5xx) y conviene que Mercado Pago vuelva a notificar.
 */
function conciliar_pago(string $pagoExterno): array {
    if ($pagoExterno === '' || !ctype_digit($pagoExterno)) {
        return [false, 'Identificador de pago no válido.', false];
    }

    [$ok, $resp, $http] = mp_api('GET', '/v1/payments/' . $pagoExterno);
    if (!$ok) return [false, (string) $resp, $http === 0 || $http === 429 || $http >= 500];

    $referencia = (string) ($resp['external_reference'] ?? '');
    $p = fila('SELECT * FROM pagos WHERE pago_externo = ?', [$pagoExterno])
      ?: ($referencia !== '' ? fila('SELECT * FROM pagos WHERE referencia = ? ORDER BY id DESC LIMIT 1', [$referencia]) : null);

    $estado  = mp_estado((string) ($resp['status'] ?? ''));
    $detalle = mb_substr((string) ($resp['status_detail'] ?? ''), 0, 80);
    $crudo   = mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE) ?: '', 0, 60000);
    $metodo  = mb_substr((string) ($resp['payment_method_id'] ?? ''), 0, 40) ?: null;
    $cuotas  = isset($resp['installments']) ? min(255, max(0, (int) $resp['installments'])) : null;

    if (!$p) {
        // Un pago que no nació en el portal, por ejemplo un link de pago. Se
        // registra para que la administración lo vea, pero no salda nada.
        $id = insertar('pagos', [
            'referencia'     => $referencia !== '' ? mb_substr($referencia, 0, 60) : ('MP-' . $pagoExterno),
            'pago_externo'   => $pagoExterno,
            'monto'          => (float) ($resp['transaction_amount'] ?? 0),
            'moneda'         => mb_substr((string) ($resp['currency_id'] ?? 'COP'), 0, 3),
            'estado'         => $estado,
            'estado_detalle' => $detalle,
            'metodo'         => $metodo,
            'cuotas'         => $cuotas,
            'entorno'        => mp_entorno(),
            'respuesta'      => $crudo,
        ]);
        return [true, 'Pago externo registrado (#' . $id . ').', false];
    }

    $mismoPago = (string) $p['pago_externo'] === '' || (string) $p['pago_externo'] === $pagoExterno;

    // Un aprobado retenido (monto o moneda que no cuadran) no saldó nada: si
    // luego llega un pago correcto para la misma referencia, ese sí vale y no
    // debe confundirse con un cobro duplicado.
    $retenido = in_array((string) $p['estado_detalle'], ['monto_no_coincide', 'moneda_no_coincide', 'pago_de_prueba'], true);

    // --- Un pago aprobado no retrocede ----------------------------------------
    if ($p['estado'] === 'aprobado' && !$retenido) {
        if (!$mismoPago) {
            if ($estado === 'aprobado') {
                pago_duplicado_avisar($p, $pagoExterno, $resp);
                return [true, 'Cobro duplicado detectado y avisado a la administración.', false];
            }
            return [true, 'Intento anterior ignorado: el cobro ya estaba aprobado con el pago ' . $p['pago_externo'] . '.', false];
        }
        if (!in_array($estado, ['aprobado', 'devuelto', 'contracargo'], true)) {
            return [true, 'Notificación atrasada ignorada: el pago ya estaba aprobado.', false];
        }
    }

    // --- Lo cobrado tiene que ser lo facturado --------------------------------
    $problema = $estado === 'aprobado' ? pago_problema($p, $resp) : null;
    if ($problema) $detalle = $problema;

    actualizar('pagos', [
        'pago_externo'   => $pagoExterno,
        'estado'         => $estado,
        'estado_detalle' => $detalle,
        'metodo'         => $metodo,
        'cuotas'         => $cuotas,
        'respuesta'      => $crudo,
    ], 'id = :id', ['id' => $p['id']]);

    if ($estado === 'aprobado') {
        if ($problema) {
            // Solo se avisa la primera vez, no en cada reintento de la notificación.
            if ($p['estado_detalle'] !== $problema) {
                avisar_admins('Pago por verificar: ' . $p['referencia'],
                    'Mercado Pago aprobó ' . moneda((float) ($resp['transaction_amount'] ?? 0), (string) ($resp['currency_id'] ?? ''))
                    . ' pero la factura es de ' . moneda((float) $p['monto'], (string) $p['moneda'])
                    . ' (' . $problema . '). No se saldó la factura.', 'aviso');
                auditar('pago_no_coincide', 'pagos', (int) $p['id'], $problema . ' · pago ' . $pagoExterno);
            }
            return [true, 'Pago ' . $pagoExterno . ' aprobado pero retenido: ' . $problema . '.', false];
        }
        aplicar_pago_aprobado((int) $p['id']);
    }

    // Una devolución o un contracargo reabren la factura y suspenden la licencia.
    if (in_array($estado, ['devuelto', 'contracargo'], true) && $p['factura_id']) {
        factura_revertir_pago((int) $p['factura_id'], $estado, $pagoExterno);
    }
    return [true, 'Pago ' . $pagoExterno . ' sincronizado: ' . $estado . '.', false];
}

/** Salda la factura de un pago aprobado. Idempotente. */
function aplicar_pago_aprobado(int $pagoId): void {
    $p = fila('SELECT * FROM pagos WHERE id = ?', [$pagoId]);
    if (!$p || $p['estado'] !== 'aprobado' || !$p['factura_id']) return;
    factura_marcar_pagada((int) $p['factura_id'], (string) $p['pasarela'], (string) $p['pago_externo']);
}

function avisar_admins(string $titulo, string $mensaje, string $tipo = 'info', string $url = 'portal/admin/facturas.php'): void {
    foreach (filas('SELECT id FROM usuarios WHERE rol = "admin" AND estado = "activo"') as $a) {
        notificar((int) $a['id'], $titulo, $mensaje, $url, $tipo);
    }
}

/** Meses que compró una factura: los fijados al emitirla o, si faltan, los del plan. */
function factura_meses(array $f): int {
    if ((int) ($f['meses'] ?? 0) > 0) return (int) $f['meses'];
    $plan = (string) valor('SELECT plan FROM licencias WHERE id = ?', [$f['licencia_id']], 'escuela');
    return plan_catalogo()[$plan]['meses'] ?? 12;
}

/**
 * Da por pagada una factura y aplica lo que se pagó. Idempotente.
 *
 * Es la única vía para saldar una factura: la usan el webhook de Mercado Pago
 * y el «Marcar pagada» del administrador, así un pago por transferencia tiene
 * exactamente el mismo efecto que uno por la pasarela.
 */
function factura_marcar_pagada(int $facturaId, string $pasarela, ?string $referenciaPago = null): bool {
    $f = fila('SELECT * FROM facturas WHERE id = ?', [$facturaId]);
    if (!$f || $f['estado'] === 'pagada') return false;

    if ($f['estado'] === 'anulada') {
        // Entró dinero para una factura que ya no vale: lo decide una persona.
        avisar_admins('Pago sobre una factura anulada: ' . $f['numero'],
            'Se recibió un pago (' . $pasarela . ') para una factura anulada. Revisa si hay que reembolsar.', 'aviso');
        auditar('pago_factura_anulada', 'facturas', (int) $f['id'], (string) $referenciaPago);
        return false;
    }

    actualizar('facturas', [
        'estado'          => 'pagada',
        'referencia_pago' => $referenciaPago !== null ? mb_substr($referenciaPago, 0, 60) : null,
        'pasarela'        => mb_substr($pasarela, 0, 30),
        'pagada_en'       => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $f['id']]);

    if ($f['licencia_id']) licencia_aplicar_pago($f);

    notificar((int) $f['cliente_id'], 'Pago recibido',
        'La factura ' . $f['numero'] . ' quedó pagada.', 'portal/cliente/licencias.php', 'logro');
    avisar_admins('Pago recibido: ' . $f['numero'],
        moneda((float) $f['monto'], $f['moneda']) . ' · ' . $f['concepto'] . ' · ' . $pasarela);
    auditar('factura_pagada', 'facturas', (int) $f['id'], $pasarela . ' · ' . $referenciaPago);
    return true;
}

/** Activa o extiende la licencia de una factura pagada, según su tipo. */
function licencia_aplicar_pago(array $f): void {
    $l = fila('SELECT * FROM licencias WHERE id = ?', [$f['licencia_id']]);
    if (!$l) return;
    $meses = factura_meses($f);

    switch ($f['tipo'] ?? 'manual') {
        case 'renovacion':
            extender_licencia((int) $l['id'], $meses);
            return;

        case 'compra':
            // La vigencia empieza el día del pago, no el día en que se hizo el
            // pedido: quien paga una semana después no pierde esa semana.
            actualizar('licencias', [
                'estado'     => 'activa',
                'emitida_en' => date('Y-m-d'),
                'vence_en'   => date('Y-m-d', strtotime('+' . $meses . ' months')),
                'notas'      => null,
            ], 'id = :id', ['id' => $l['id']]);
            auditar('licencia_activada_por_pago', 'licencias', (int) $l['id'], $meses . ' mes(es)');
            return;

        default:
            // Factura manual con licencia: se reactiva si estaba suspendida y se
            // respetan las fechas que haya puesto la administración.
            if ($l['estado'] === 'suspendida') {
                actualizar('licencias', ['estado' => 'activa'], 'id = :id', ['id' => $l['id']]);
                auditar('licencia_reactivada_por_pago', 'licencias', (int) $l['id']);
            }
    }
}

/**
 * Extiende la vigencia de una licencia al pagar su renovación: desde su fecha
 * de vencimiento si aún no ha vencido, o desde hoy si ya venció. Si el plan
 * ofrece hoy más cupo del que tenía la licencia, se amplía; nunca se reduce.
 */
function extender_licencia(int $licenciaId, ?int $meses = null): void {
    $l = fila('SELECT * FROM licencias WHERE id = ?', [$licenciaId]);
    if (!$l) return;
    $cat = plan_catalogo()[$l['plan']] ?? PLANES_FABRICA['escuela'];
    $meses ??= $cat['meses'];
    $desde = max(time(), strtotime((string) $l['vence_en']) ?: 0);
    actualizar('licencias', [
        'vence_en' => date('Y-m-d', strtotime('+' . $meses . ' months', $desde)),
        'estado'   => 'activa',
        'cupo'     => max((int) $l['cupo'], (int) $cat['cupo']),
    ], 'id = :id', ['id' => $licenciaId]);
    auditar('licencia_extendida', 'licencias', $licenciaId, $meses . ' mes(es)');
}

/** Devolución o contracargo: la factura vuelve a quedar pendiente y la licencia se suspende. */
function factura_revertir_pago(int $facturaId, string $motivo, string $pagoExterno): void {
    $f = fila('SELECT * FROM facturas WHERE id = ?', [$facturaId]);
    if (!$f || $f['estado'] !== 'pagada') return;

    actualizar('facturas', ['estado' => 'pendiente', 'pagada_en' => null], 'id = :id', ['id' => $f['id']]);
    if ($f['licencia_id']) {
        actualizar('licencias', [
            'estado' => 'suspendida',
            'notas'  => mb_substr('Suspendida: pago ' . $motivo . ' (' . $pagoExterno . ').', 0, 300),
        ], 'id = :id', ['id' => $f['licencia_id']]);
    }
    notificar((int) $f['cliente_id'], 'Pago revertido',
        'El pago de la factura ' . $f['numero'] . ' fue ' . ($motivo === 'devuelto' ? 'devuelto' : 'desconocido por el banco')
        . '. La licencia queda suspendida hasta regularizarlo.', 'portal/cliente/facturas.php', 'aviso');
    avisar_admins('Pago revertido: ' . $f['numero'],
        'Pago ' . $pagoExterno . ' ' . $motivo . '. La factura volvió a pendiente y la licencia quedó suspendida.', 'aviso');
    auditar('pago_revertido', 'facturas', (int) $f['id'], $motivo . ' · ' . $pagoExterno);
}

/** Dos pagos aprobados para la misma factura: alguien pagó dos veces. */
function pago_duplicado_avisar(array $p, string $pagoExterno, array $resp): void {
    $ya = valor('SELECT COUNT(*) FROM auditoria WHERE accion = "pago_duplicado" AND detalle LIKE ?', ['%' . $pagoExterno . '%'], 0);
    if ($ya) return;
    avisar_admins('Cobro duplicado: ' . $p['referencia'],
        'El pago ' . $pagoExterno . ' (' . moneda((float) ($resp['transaction_amount'] ?? 0), (string) ($resp['currency_id'] ?? 'COP'))
        . ') llegó cuando la factura ya estaba saldada con el pago ' . $p['pago_externo'] . '. Hay que reembolsarlo desde Mercado Pago.',
        'aviso');
    auditar('pago_duplicado', 'pagos', (int) $p['id'], 'pago ' . $pagoExterno . ' sobre ' . $p['pago_externo']);
}

// ======================================================== facturas y compras ==

/** Número de factura consecutivo y único del año en curso. */
function nuevo_numero_factura(): string {
    $anio = date('Y');
    for ($i = 0; $i < 20; $i++) {
        $ultimo = (int) valor(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)), 0)
               FROM facturas WHERE numero LIKE ?", ['VCP-' . $anio . '-%'], 0);
        $numero = 'VCP-' . $anio . '-' . str_pad((string) ($ultimo + 1 + $i), 4, '0', STR_PAD_LEFT);
        if (!valor('SELECT id FROM facturas WHERE numero = ?', [$numero])) return $numero;
    }
    return 'VCP-' . $anio . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Una factura pendiente que aún no tiene ningún intento de pago se pone al día
 * con el precio vigente. Si ya hubo un intento, se respeta el valor con el que
 * el cliente empezó a pagar.
 */
function factura_al_precio_vigente(array $f, array $cat, int $cupo, bool $renovacion): array {
    $cambios = [];
    if (!valor('SELECT id FROM pagos WHERE factura_id = ? LIMIT 1', [$f['id']])) {
        if ((float) $f['monto'] !== (float) $cat['precio'] || (int) ($f['meses'] ?? 0) !== $cat['meses']) {
            $cambios += [
                'monto'    => $cat['precio'],
                'meses'    => $cat['meses'],
                'concepto' => plan_concepto($cat, $cupo, $renovacion),
            ];
        }
    }
    if ($f['estado'] === 'vencida') {
        $cambios += ['estado' => 'pendiente', 'vence_en' => date('Y-m-d', strtotime('+15 days'))];
    }
    if ($cambios) {
        actualizar('facturas', $cambios, 'id = :id', ['id' => $f['id']]);
        $f = fila('SELECT * FROM facturas WHERE id = ?', [$f['id']]) ?? $f;
    }
    return $f;
}

/**
 * Pedido de compra en línea: licencia suspendida hasta el pago y su factura.
 * Si el cliente ya tiene un pedido igual sin pagar de las últimas dos semanas,
 * lo reutiliza en vez de sembrar licencias y facturas duplicadas.
 */
function compra_crear(string $plan, int $clienteId, ?int $colegioId): array {
    $catalogo = plan_catalogo();
    if (!isset($catalogo[$plan])) $plan = 'escuela';
    $cat = $catalogo[$plan];

    $previa = fila('SELECT f.* FROM facturas f JOIN licencias l ON l.id = f.licencia_id
                     WHERE f.cliente_id = ? AND f.tipo = "compra" AND f.estado IN ("pendiente","vencida")
                       AND l.plan = ? AND l.estado = "suspendida" AND f.emitida_en >= ?
                  ORDER BY f.id DESC LIMIT 1', [$clienteId, $plan, date('Y-m-d', strtotime('-14 days'))]);
    if ($previa) {
        return factura_al_precio_vigente($previa, $cat, $cat['cupo'], false);
    }

    $licenciaId = insertar('licencias', [
        'colegio_id' => $colegioId ?: null,
        'cliente_id' => $clienteId,
        'clave'      => generar_clave_licencia($plan),
        'plan'       => $plan,
        'cupo'       => $cat['cupo'],
        // Provisional: al acreditarse el pago la vigencia se recalcula desde ese día.
        'emitida_en' => date('Y-m-d'),
        'vence_en'   => date('Y-m-d', strtotime('+' . $cat['meses'] . ' months')),
        'estado'     => 'suspendida',
        'notas'      => 'Compra en línea pendiente de pago.',
    ]);

    $facturaId = insertar('facturas', [
        'cliente_id'  => $clienteId,
        'licencia_id' => $licenciaId,
        'tipo'        => 'compra',
        'meses'       => $cat['meses'],
        'numero'      => nuevo_numero_factura(),
        'concepto'    => plan_concepto($cat, $cat['cupo']),
        'monto'       => $cat['precio'],
        'moneda'      => 'COP',
        'estado'      => 'pendiente',
        'emitida_en'  => date('Y-m-d'),
        'vence_en'    => date('Y-m-d', strtotime('+15 days')),
    ]);
    auditar('compra_iniciada', 'facturas', $facturaId, $plan . ' · ' . plan_pesos($cat['precio']));
    return fila('SELECT * FROM facturas WHERE id = ?', [$facturaId]) ?? [];
}

/**
 * Emite la factura de renovación de una licencia. Si ya hay una pendiente para
 * esa licencia, devuelve esa, puesta al día con el precio vigente.
 */
function factura_de_renovacion(array $licencia, int $clienteId): array {
    $cat = plan_catalogo()[$licencia['plan']] ?? PLANES_FABRICA['escuela'];
    $cupo = max((int) $licencia['cupo'], $cat['cupo']);

    $pendiente = fila('SELECT * FROM facturas
                        WHERE licencia_id = ? AND cliente_id = ? AND tipo = "renovacion" AND estado IN ("pendiente","vencida")
                     ORDER BY id DESC LIMIT 1', [$licencia['id'], $clienteId]);
    if ($pendiente) return factura_al_precio_vigente($pendiente, $cat, $cupo, true);

    $id = insertar('facturas', [
        'cliente_id'  => $clienteId,
        'licencia_id' => (int) $licencia['id'],
        'tipo'        => 'renovacion',
        'meses'       => $cat['meses'],
        'numero'      => nuevo_numero_factura(),
        'concepto'    => plan_concepto($cat, $cupo, true),
        'monto'       => $cat['precio'],
        'moneda'      => 'COP',
        'estado'      => 'pendiente',
        'emitida_en'  => date('Y-m-d'),
        'vence_en'    => date('Y-m-d', strtotime('+15 days')),
    ]);
    auditar('factura_renovacion', 'facturas', $id, (string) $licencia['clave']);
    return fila('SELECT * FROM facturas WHERE id = ?', [$id]) ?? [];
}
