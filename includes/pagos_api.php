<?php
/**
 * Cobro con Mercado Pago (Checkout API).
 *
 * El navegador tokeniza la tarjeta con el SDK oficial y envía únicamente el
 * token: los datos de la tarjeta nunca pasan por este servidor.
 *
 * Contrato de la API verificado contra la referencia oficial:
 *   POST https://api.mercadopago.com/v1/payments
 *   Cabeceras: Authorization: Bearer, Content-Type, X-Idempotency-Key
 */

declare(strict_types=1);

require_once __DIR__ . '/pagos.php';

/**
 * Llama a la API de Mercado Pago. Devuelve [ok, datos|mensaje, codigoHttp].
 * $idempotencia evita cobros duplicados si la petición se reintenta.
 */
function mp_api(string $metodo, string $ruta, ?array $cuerpo = null, ?string $idempotencia = null): array {
    if (mp_access_token() === '') {
        return [false, 'La pasarela de pagos no está configurada.', 0];
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

/** Mensaje entendible para el cliente según el motivo que devuelve la pasarela. */
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
        default                                => 'El pago no pudo completarse.',
    };
}

/** Referencia propia que viaja como external_reference y permite conciliar. */
function pago_referencia(int $facturaId): string {
    return 'VCP-F' . $facturaId . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Cobra una factura con el token de tarjeta generado en el navegador.
 * Devuelve [aprobado, mensaje, idPagoLocal].
 */
function cobrar_factura(array $factura, array $form, int $clienteId): array {
    $referencia = pago_referencia((int) $factura['id']);

    $pagoLocal = insertar('pagos', [
        'factura_id' => (int) $factura['id'],
        'cliente_id' => $clienteId ?: null,
        'referencia' => $referencia,
        'monto'      => (float) $factura['monto'],
        'moneda'     => $factura['moneda'],
        'estado'     => 'pendiente',
        'entorno'    => mp_entorno(),
    ]);

    $cuerpo = [
        'transaction_amount' => (float) $factura['monto'],
        'token'              => $form['token'],
        'description'        => mb_substr('VCodePro · ' . $factura['concepto'], 0, 250),
        'installments'       => max(1, (int) $form['installments']),
        'payment_method_id'  => $form['payment_method_id'],
        'external_reference' => $referencia,
        'notification_url'   => mp_url_webhook(),
        'payer'              => ['email' => $form['email']],
    ];
    if (!empty($form['issuer_id']))  $cuerpo['issuer_id'] = $form['issuer_id'];
    if (!empty($form['doc_numero'])) {
        $cuerpo['payer']['identification'] = [
            'type'   => $form['doc_tipo'] ?: 'CC',
            'number' => $form['doc_numero'],
        ];
    }

    // La clave de idempotencia es la referencia: si el navegador reenvía el
    // formulario, Mercado Pago devuelve el mismo pago en vez de cobrar dos veces.
    [$ok, $resp] = mp_api('POST', '/v1/payments', $cuerpo, $referencia);

    if (!$ok) {
        actualizar('pagos', [
            'estado'         => 'rechazado',
            'estado_detalle' => mb_substr((string) $resp, 0, 80),
            'respuesta'      => (string) $resp,
        ], 'id = :id', ['id' => $pagoLocal]);
        auditar('pago_error', 'pagos', $pagoLocal, (string) $resp);
        return [false, (string) $resp, $pagoLocal];
    }

    $estado  = mp_estado((string) ($resp['status'] ?? ''));
    $detalle = (string) ($resp['status_detail'] ?? '');

    actualizar('pagos', [
        'pago_externo'   => (string) ($resp['id'] ?? ''),
        'estado'         => $estado,
        'estado_detalle' => mb_substr($detalle, 0, 80),
        'metodo'         => mb_substr((string) ($resp['payment_method_id'] ?? ''), 0, 40),
        'cuotas'         => (int) ($resp['installments'] ?? 1),
        'respuesta'      => mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE) ?: '', 0, 60000),
    ], 'id = :id', ['id' => $pagoLocal]);

    if ($estado === 'aprobado') aplicar_pago_aprobado($pagoLocal);

    auditar('pago_' . $estado, 'pagos', $pagoLocal, $factura['numero'] . ' · ' . $detalle);
    return [$estado === 'aprobado', mp_motivo($detalle), $pagoLocal];
}

/**
 * Marca la factura como pagada y reactiva su licencia.
 * Es idempotente: repetirla no cambia nada.
 */
function aplicar_pago_aprobado(int $pagoId): void {
    $p = fila('SELECT * FROM pagos WHERE id = ?', [$pagoId]);
    if (!$p || $p['estado'] !== 'aprobado' || !$p['factura_id']) return;

    $f = fila('SELECT * FROM facturas WHERE id = ?', [$p['factura_id']]);
    if (!$f || $f['estado'] === 'pagada') return;

    actualizar('facturas', [
        'estado'          => 'pagada',
        'referencia_pago' => $p['pago_externo'],
        'pasarela'        => $p['pasarela'],
        'pagada_en'       => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $f['id']]);

    // La licencia asociada se reactiva y, si era una renovación, se extiende.
    if ($f['licencia_id']) {
        if (stripos((string) $f['concepto'], 'renovaci') !== false) {
            extender_licencia((int) $f['licencia_id']);
        } else {
            $l = fila('SELECT * FROM licencias WHERE id = ?', [$f['licencia_id']]);
            if ($l && $l['estado'] !== 'activa') {
                actualizar('licencias', ['estado' => 'activa'], 'id = :id', ['id' => $l['id']]);
            }
        }
    }

    if ($p['cliente_id']) {
        notificar((int) $p['cliente_id'], 'Pago recibido',
            'La factura ' . $f['numero'] . ' quedó pagada.', 'portal/cliente/facturas.php', 'logro');
    }
    foreach (filas('SELECT id FROM usuarios WHERE rol = "admin" AND estado = "activo"') as $a) {
        notificar((int) $a['id'], 'Pago recibido: ' . $f['numero'],
            moneda((float) $f['monto'], $f['moneda']) . ' · ' . $f['concepto'], 'portal/admin/facturas.php');
    }
    auditar('factura_pagada', 'facturas', (int) $f['id'], (string) $p['pago_externo']);
}

/**
 * Consulta un pago en Mercado Pago y sincroniza el estado local.
 * Es lo que ejecuta el webhook al recibir una notificación.
 */
function conciliar_pago(string $pagoExterno): array {
    [$ok, $resp] = mp_api('GET', '/v1/payments/' . rawurlencode($pagoExterno));
    if (!$ok) return [false, (string) $resp];

    $referencia = (string) ($resp['external_reference'] ?? '');
    $p = fila('SELECT * FROM pagos WHERE pago_externo = ? OR referencia = ?', [$pagoExterno, $referencia]);

    $estado  = mp_estado((string) ($resp['status'] ?? ''));
    $detalle = mb_substr((string) ($resp['status_detail'] ?? ''), 0, 80);
    $crudo   = mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE) ?: '', 0, 60000);

    if (!$p) {
        // Un pago que no nació en el portal, por ejemplo un link de pago.
        $id = insertar('pagos', [
            'referencia'     => $referencia !== '' ? $referencia : ('MP-' . $pagoExterno),
            'pago_externo'   => $pagoExterno,
            'monto'          => (float) ($resp['transaction_amount'] ?? 0),
            'moneda'         => mb_substr((string) ($resp['currency_id'] ?? 'COP'), 0, 3),
            'estado'         => $estado,
            'estado_detalle' => $detalle,
            'metodo'         => mb_substr((string) ($resp['payment_method_id'] ?? ''), 0, 40),
            'entorno'        => mp_entorno(),
            'respuesta'      => $crudo,
        ]);
        return [true, 'Pago externo registrado (#' . $id . ').'];
    }

    actualizar('pagos', [
        'pago_externo'   => $pagoExterno,
        'estado'         => $estado,
        'estado_detalle' => $detalle,
        'respuesta'      => $crudo,
    ], 'id = :id', ['id' => $p['id']]);

    if ($estado === 'aprobado') aplicar_pago_aprobado((int) $p['id']);

    // Una devolución o un contracargo reabren la factura.
    if (in_array($estado, ['devuelto', 'contracargo'], true) && $p['factura_id']) {
        actualizar('facturas', ['estado' => 'pendiente', 'pagada_en' => null], 'id = :id', ['id' => $p['factura_id']]);
        foreach (filas('SELECT id FROM usuarios WHERE rol = "admin" AND estado = "activo"') as $a) {
            notificar((int) $a['id'], 'Pago revertido',
                'Revisa la factura asociada al pago ' . $pagoExterno, 'portal/admin/facturas.php', 'aviso');
        }
    }
    return [true, 'Pago ' . $pagoExterno . ' sincronizado: ' . $estado . '.'];
}

// ======================================================== planes y facturas ==

/** Catálogo comercial: precio, cupo, vigencia y nombre visible de cada plan. */
function plan_catalogo(): array {
    return [
        'personal' => ['nombre' => 'Personal',           'precio' => 150000.0,   'cupo' => 1,   'meses' => 1],
        'escuela'  => ['nombre' => 'Escuela',            'precio' => 5000000.0,  'cupo' => 100, 'meses' => 12],
        'sitio'    => ['nombre' => 'Licencia de Sitio',  'precio' => 20000000.0, 'cupo' => 500, 'meses' => 12],
    ];
}

/** Elige el plan más económico que cubra el número de licencias pedido. */
function plan_sugerido(int $licencias): string {
    if ($licencias <= 1) return 'personal';
    if ($licencias <= 100) return 'escuela';
    return 'sitio';
}

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
 * Emite la factura de renovación de una licencia. Si ya hay una pendiente
 * para esa licencia, devuelve esa en vez de duplicarla.
 */
function factura_de_renovacion(array $licencia, int $clienteId): array {
    $pendiente = fila('SELECT * FROM facturas
                        WHERE licencia_id = ? AND cliente_id = ? AND estado IN ("pendiente","vencida")
                     ORDER BY id DESC LIMIT 1', [$licencia['id'], $clienteId]);
    if ($pendiente) return $pendiente;

    $cat = plan_catalogo()[$licencia['plan']] ?? plan_catalogo()['escuela'];
    $meses = $cat['meses'];
    $periodo = $meses === 1 ? 'un mes' : 'doce meses';

    $id = insertar('facturas', [
        'cliente_id'  => $clienteId,
        'licencia_id' => (int) $licencia['id'],
        'numero'      => nuevo_numero_factura(),
        'concepto'    => 'Renovación de la licencia ' . $cat['nombre'] . ' · ' . (int) $licencia['cupo']
                       . ' puestos · ' . $periodo,
        'monto'       => $cat['precio'],
        'moneda'      => 'COP',
        'estado'      => 'pendiente',
        'emitida_en'  => date('Y-m-d'),
        'vence_en'    => date('Y-m-d', strtotime('+15 days')),
    ]);
    auditar('factura_renovacion', 'facturas', $id, (string) $licencia['clave']);
    return fila('SELECT * FROM facturas WHERE id = ?', [$id]) ?? [];
}

/**
 * Extiende la vigencia de una licencia cuando se paga su renovación.
 * Se llama desde aplicar_pago_aprobado().
 */
function extender_licencia(int $licenciaId): void {
    $l = fila('SELECT * FROM licencias WHERE id = ?', [$licenciaId]);
    if (!$l) return;
    $cat = plan_catalogo()[$l['plan']] ?? plan_catalogo()['escuela'];
    $desde = max(time(), strtotime((string) $l['vence_en']));
    actualizar('licencias', [
        'vence_en' => date('Y-m-d', strtotime('+' . $cat['meses'] . ' months', $desde)),
        'estado'   => 'activa',
    ], 'id = :id', ['id' => $licenciaId]);
    auditar('licencia_extendida', 'licencias', $licenciaId);
}
