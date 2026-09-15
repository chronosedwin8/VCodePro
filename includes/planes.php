<?php
/**
 * Catálogo comercial de VCodePro: planes, precios, cupos y vigencias.
 *
 * Es la **única fuente de precios** del proyecto. De aquí salen:
 *   - lo que se cobra en Mercado Pago (comprar.php y las renovaciones),
 *   - los precios de la portada y de la página de precios, a través de
 *     portal/api/precios.php y assets/js/planes.js,
 *   - las tablas del portal del cliente y del administrador.
 *
 * Antes había cuatro copias —PHP, dos páginas HTML y dos scripts— que no se
 * hablaban: cambiar una no cambiaba lo que se cobraba.
 *
 * La administración los edita en Admin → Precios y se guardan en `ajustes`,
 * clave `planes`. Si un valor guardado no es válido se usa el de fábrica: un
 * precio roto nunca debe llegar a la pasarela.
 *
 * Las claves de plan (personal, escuela, sitio) son fijas porque son el ENUM
 * de `licencias.plan`. Lo editable es el nombre, el precio, el cupo y la
 * periodicidad.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

const PLANES_FABRICA = [
    'personal' => ['nombre' => 'Personal',          'precio' => 150000,   'cupo' => 1,   'meses' => 1],
    'escuela'  => ['nombre' => 'Escuela',           'precio' => 5000000,  'cupo' => 100, 'meses' => 12],
    'sitio'    => ['nombre' => 'Licencia de Sitio', 'precio' => 20000000, 'cupo' => 500, 'meses' => 12],
];

/** Límites de lo que acepta el editor de precios. */
const PLAN_PRECIO_MIN = 1000;
const PLAN_PRECIO_MAX = 500000000;
const PLAN_CUPO_MAX   = 5000;

/** Catálogo vigente: lo guardado por la administración, o el de fábrica. */
function plan_catalogo(): array {
    $guardado = json_decode((string) ajuste('planes', ''), true);
    $out = [];
    foreach (PLANES_FABRICA as $clave => $fabrica) {
        $p = is_array($guardado[$clave] ?? null) ? $guardado[$clave] + $fabrica : $fabrica;
        [$ok] = plan_validar($p);
        $out[$clave] = $ok ? plan_normalizar($p) : $fabrica;
    }
    return $out;
}

function plan_normalizar(array $p): array {
    return [
        'nombre' => trim(utf8_limpio((string) $p['nombre'])),
        'precio' => (int) round((float) $p['precio']),
        'cupo'   => (int) $p['cupo'],
        'meses'  => (int) $p['meses'],
    ];
}

/** Devuelve [true, []] o [false, ['campo' => 'motivo', …]]. */
function plan_validar(array $p): array {
    $e = [];

    $nombre = trim(utf8_limpio((string) ($p['nombre'] ?? '')));
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 40) {
        $e['nombre'] = 'El nombre debe tener entre 2 y 40 caracteres.';
    }

    $precio = $p['precio'] ?? null;
    if (!is_numeric($precio) || (float) $precio != floor((float) $precio)) {
        $e['precio'] = 'El precio va en pesos enteros, sin centavos ni puntos.';
    } elseif ((float) $precio < PLAN_PRECIO_MIN || (float) $precio > PLAN_PRECIO_MAX) {
        $e['precio'] = 'El precio debe estar entre ' . plan_pesos(PLAN_PRECIO_MIN) . ' y ' . plan_pesos(PLAN_PRECIO_MAX) . '.';
    }

    $cupo = $p['cupo'] ?? null;
    if (!is_numeric($cupo) || (int) $cupo != (float) $cupo || (int) $cupo < 1 || (int) $cupo > PLAN_CUPO_MAX) {
        $e['cupo'] = 'El cupo debe ser un número entero entre 1 y ' . PLAN_CUPO_MAX . '.';
    }

    if (!in_array((int) ($p['meses'] ?? 0), [1, 12], true)) {
        $e['meses'] = 'La periodicidad es mensual o anual.';
    }

    return [!$e, $e];
}

/**
 * Guarda el catálogo completo. Todo o nada: si un plan no es válido no se
 * guarda ninguno. Devuelve [true, []] o [false, ['plan' => ['campo' => motivo]]].
 */
function plan_guardar(array $planes): array {
    $antes = plan_catalogo();
    $nuevo = [];
    $errores = [];
    foreach (PLANES_FABRICA as $clave => $_) {
        $p = is_array($planes[$clave] ?? null) ? $planes[$clave] : [];
        [$ok, $e] = plan_validar($p);
        if (!$ok) { $errores[$clave] = $e; continue; }
        $nuevo[$clave] = plan_normalizar($p);
    }
    if ($errores) return [false, $errores];

    guardar_ajuste('planes', (string) json_encode($nuevo, JSON_UNESCAPED_UNICODE));

    foreach ($nuevo as $clave => $p) {
        $cambios = [];
        foreach (['nombre', 'precio', 'cupo', 'meses'] as $k) {
            if ($antes[$clave][$k] !== $p[$k]) $cambios[] = $k . ': ' . $antes[$clave][$k] . ' → ' . $p[$k];
        }
        if ($cambios) auditar('precio_cambiado', 'planes', null, $clave . ' · ' . implode(' · ', $cambios));
    }
    return [true, []];
}

/** 5000000 → «$5.000.000», como se escribe en la web. */
function plan_pesos(int|float $valor): string {
    return '$' . number_format((float) round($valor), 0, ',', '.');
}

function plan_periodo(array $p): string       { return $p['meses'] === 1 ? 'mes' : 'año'; }
function plan_periodo_largo(array $p): string { return $p['meses'] === 1 ? 'un mes' : 'doce meses'; }

/** Costo de una licencia al mes, el número que la web usa para comparar planes. */
function plan_por_licencia_mes(array $p): int {
    return (int) round($p['precio'] / max(1, $p['cupo']) / max(1, $p['meses']));
}

/** «1 puesto» / «100 puestos». */
function plan_puestos(int $cupo): string {
    return $cupo . ($cupo === 1 ? ' puesto' : ' puestos');
}

/** Concepto de factura, igual en compras, renovaciones y en la web. */
function plan_concepto(array $p, int $cupo, bool $renovacion = false): string {
    return ($renovacion ? 'Renovación de la licencia ' : 'Licencia ') . $p['nombre']
         . ' · ' . plan_puestos($cupo) . ' · ' . plan_periodo_largo($p);
}

/** Elige el plan más económico que cubra el número de licencias pedido. */
function plan_sugerido(int $licencias): string {
    $cat = plan_catalogo();
    foreach (['personal', 'escuela', 'sitio'] as $k) {
        if ($licencias <= $cat[$k]['cupo']) return $k;
    }
    return 'sitio';
}

/** Lo que se publica en la web. Nada interno. */
function planes_publicos(): array {
    $out = [];
    foreach (plan_catalogo() as $clave => $p) {
        $unitario = plan_por_licencia_mes($p);
        $out[$clave] = $p + [
            'periodo'                => plan_periodo($p),
            'precio_texto'           => plan_pesos($p['precio']),
            'por_licencia_mes'       => $unitario,
            'por_licencia_mes_texto' => plan_pesos($unitario),
            'comprar'                => 'comprar.php?plan=' . $clave,
        ];
    }
    return $out;
}

/**
 * Pasa a «vencida» lo que ya venció por fecha: licencias activas y facturas
 * pendientes.
 *
 * Antes eso solo ocurría cuando un administrador abría su página, así que una
 * licencia Personal mensual seguía activa indefinidamente si nadie entraba al
 * panel. Ahora corre al cargar cualquier página del portal, como mucho una vez
 * cada diez minutos para no escribir en la base en cada visita.
 */
function mantener_vigencias(bool $forzar = false): void {
    static $hecho = false;
    if ($hecho && !$forzar) return;
    $hecho = true;

    if (!$forzar && time() - (int) ajuste('vigencias_revisadas', '0') < 600) return;

    try {
        $st = q('UPDATE licencias SET estado = "vencida" WHERE estado = "activa" AND vence_en < CURDATE()');
        $licencias = is_object($st) ? $st->rowCount() : 0;
        q('UPDATE facturas SET estado = "vencida" WHERE estado = "pendiente" AND vence_en < CURDATE()');
        guardar_ajuste('vigencias_revisadas', (string) time());
        if ($licencias) auditar('licencias_vencidas', 'licencias', null, $licencias . ' licencia(s) vencida(s) por fecha');
    } catch (Throwable $e) {
        // Nunca debe romper la página que se está cargando.
    }
}
