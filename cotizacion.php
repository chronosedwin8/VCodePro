<?php
/**
 * Cotización automática en PDF.
 *
 * La calculadora de precios.html envía plan, licencias y periodo. El cálculo
 * se repite aquí con plan_catalogo(), así el documento lleva los precios que
 * de verdad se cobran y no los que tuviera cargados el navegador.
 *
 * La lógica es la misma que la de assets/js/precios.js: si se cambia una, hay
 * que cambiar la otra.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/planes.php';
require_once __DIR__ . '/includes/pdf.php';

$cat = plan_catalogo();
$licencias = max(1, min(2000, get_int('licencias', 1)));
$periodo   = get('periodo') === 'mensual' ? 'mensual' : 'anual';
$pedido    = get('plan');

// --- Cálculo (espejo de precios.js) --------------------------------------------
// Personal usa las mismas reglas que la compra (PLAN_PERSONAL_*). Aquí no se le
// pone el tope de licencias porque también sirve para comparar: «50 licencias
// Personal costarían…», aunque solo se vendan hasta PLAN_PERSONAL_MAX.
$mensualPersonal = $cat['personal']['precio'];
$anual = fn(string $k) => $cat[$k]['meses'] === 12 ? $cat[$k]['precio'] : $cat[$k]['precio'] * 12;

$costo = function (string $plan) use ($licencias, $periodo, $mensualPersonal, $anual): float {
    if ($plan === 'personal') {
        return $mensualPersonal * $licencias * ($periodo === 'anual' ? PLAN_PERSONAL_MESES_ANUAL : 1);
    }
    return $periodo === 'anual' ? $anual($plan) : $anual($plan) / 12;
};

$elegibles = [];
if ($licencias <= PLAN_PERSONAL_MAX)          $elegibles[] = 'personal';
if ($licencias <= $cat['escuela']['cupo'])    $elegibles[] = 'escuela';
if ($licencias <= $cat['sitio']['cupo'])      $elegibles[] = 'sitio';

if (!$elegibles) {
    // Más licencias que el cupo mayor: eso es un acuerdo a la medida.
    header('Location: contacto.html?motivo=sitio', true, 302);
    exit;
}

$recomendado = array_reduce($elegibles, fn($mejor, $k) => $costo($k) < $costo($mejor) ? $k : $mejor, $elegibles[0]);
$plan = in_array($pedido, $elegibles, true) ? $pedido : $recomendado;

$datos   = $cat[$plan];
$total   = $costo($plan);
$meses   = $periodo === 'anual' ? 12 : 1;
$unitario = $total / $licencias / $meses;
$ahorro  = $plan !== 'personal' ? $costo('personal') - $total : 0;

if ($plan === 'personal') {
    $detalle = $licencias . ($licencias === 1 ? ' licencia' : ' licencias') . ' × ' . plan_pesos($mensualPersonal)
             . ' al mes' . ($periodo === 'anual' ? ' × ' . PLAN_PERSONAL_MESES_ANUAL . ' meses facturados (12 meses de uso)' : '');
} else {
    $detalle = 'Tarifa única para hasta ' . $datos['cupo'] . ' licencias'
             . ($periodo === 'anual' ? ' durante 12 meses' : ', prorrateada al mes');
}

// --- Datos del documento ---------------------------------------------------------
$MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];
$larga = fn(int $t) => date('j', $t) . ' de ' . $MESES[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
$hoy = time();
$numero = 'COT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$sitio = 'https://www.vcodepro.de';

// --- Maquetación -----------------------------------------------------------------
$m = 56;                              // margen
$der = PDF_ANCHO - $m;
$pdf = pdf_nuevo('Cotización VCodePro ' . $numero);

// Banda superior
pdf_rect($pdf, 0, 0, PDF_ANCHO, 96, '#0d0f14');
pdf_texto($pdf, $m, 56, 'vcode', 24, true, '#ffffff');
pdf_texto($pdf, $m + pdf_ancho('vcode', 24, true), 56, 'pro', 24, true, '#4cc2ff');
pdf_texto($pdf, $m, 76, 'Editor con IA para las electivas de tecnología del IB', 9, false, '#9fb0c3');
pdf_texto($pdf, $der, 48, 'COTIZACIÓN', 10, true, '#9fb0c3', 'der');
pdf_texto($pdf, $der, 66, $numero, 12, true, '#ffffff', 'der');

// Título y datos generales
pdf_texto($pdf, $m, 146, 'Cotización de licenciamiento', 20, true, '#0b1220');
$y = 176;
foreach ([
    ['Fecha de emisión', $larga($hoy)],
    ['Válida hasta', $larga($hoy + 30 * 86400)],
    ['Moneda', 'Peso colombiano (COP)'],
] as [$etq, $val]) {
    pdf_texto($pdf, $m, $y, $etq, 10, false, '#56616e');
    pdf_texto($pdf, $m + 130, $y, $val, 10, false, '#1f2328');
    $y += 17;
}

// Detalle
$y += 18;
pdf_texto($pdf, $m, $y, 'DETALLE', 9, true, '#0078d4');
$y += 10;
pdf_linea($pdf, $m, $y, $der, $y, '#0078d4', 1);
$y += 20;
foreach ([
    ['Plan', $datos['nombre'] . ($plan === $recomendado ? '  ·  plan recomendado' : '')],
    ['Licencias', (string) $licencias],
    ['Periodo', $periodo === 'anual' ? 'Anual (12 meses)' : 'Mensual'],
    ['Tarifa', $detalle],
] as [$etq, $val]) {
    pdf_texto($pdf, $m, $y, $etq, 10, false, '#56616e');
    $y = pdf_parrafo($pdf, $m + 130, $y, $der - $m - 130, $val, 10, $etq === 'Plan', '#1f2328', 1.4) + 4;
    pdf_linea($pdf, $m, $y - 8, $der, $y - 8, '#e8edf3');
    $y += 8;
}

// Total
$y += 10;
pdf_rect($pdf, $m, $y, $der - $m, 74, '#f5f7fa');
pdf_rect($pdf, $m, $y, 4, 74, '#0078d4');
pdf_texto($pdf, $m + 22, $y + 32, 'Total', 13, true, '#0b1220');
pdf_texto($pdf, $m + 22 + pdf_ancho('Total ', 13, true), $y + 32, 'por ' . ($periodo === 'anual' ? 'año' : 'mes'), 10, false, '#56616e');
pdf_texto($pdf, $der - 20, $y + 36, plan_pesos($total), 24, true, '#0078d4', 'der');
pdf_texto($pdf, $m + 22, $y + 56, 'Costo por licencia al mes', 10, false, '#56616e');
pdf_texto($pdf, $der - 20, $y + 56, plan_pesos($unitario), 10, true, '#1f2328', 'der');
$y += 74;

if ($ahorro > 0) {
    $y += 22;
    pdf_texto($pdf, $m, $y, 'Ahorro: ' . plan_pesos($ahorro) . ' frente a ' . $licencias . ' licencias ' . $cat['personal']['nombre'] . '.', 10, true, '#1a7f37');
}

// Condiciones
$y += 36;
pdf_texto($pdf, $m, $y, 'CONDICIONES', 9, true, '#0078d4');
$y += 10;
pdf_linea($pdf, $m, $y, $der, $y, '#0078d4', 1);
$y += 20;
foreach ([
    'Los precios están expresados en pesos colombianos y son valores finales.',
    'Venta digital internacional facturada desde Alemania, sin impuestos añadidos.',
    'La vigencia de las licencias inicia el día en que se acredita el pago.',
    'Incluye actualizaciones y soporte durante toda la vigencia.',
    'En el extracto de la tarjeta el cobro figura como VCODEPRO.',
] as $cond) {
    pdf_texto($pdf, $m + 2, $y, '·', 12, true, '#0078d4');
    $y = pdf_parrafo($pdf, $m + 16, $y, $der - $m - 16, $cond, 10, false, '#1f2328', 1.4) + 3;
}

// Cómo contratar
$y += 16;
pdf_texto($pdf, $m, $y, 'CÓMO CONTRATAR', 9, true, '#0078d4');
$y += 10;
pdf_linea($pdf, $m, $y, $der, $y, '#0078d4', 1);
$y += 20;
$compra = $plan === 'personal'
    ? http_build_query(['plan' => $plan, 'licencias' => $licencias, 'periodo' => $periodo])
    : http_build_query(['plan' => $plan]);
$y = pdf_parrafo($pdf, $m, $y, $der - $m,
    'Pago en línea con tarjeta, PSE o efectivo a través de Mercado Pago: ' . $sitio . '/comprar.php?' . $compra, 10);
if ($plan !== 'personal' && $periodo === 'mensual') {
    $y = pdf_parrafo($pdf, $m, $y + 2, $der - $m,
        'El valor mensual es de referencia: el plan ' . $datos['nombre'] . ' se paga por año (' . plan_pesos($anual($plan)) . ').', 10);
}
$y = pdf_parrafo($pdf, $m, $y + 2, $der - $m,
    'Orden de compra, transferencia o condiciones especiales: licencias@vcodepro.de', 10);

// Pie
// Pie en dos líneas: en una sola, la frase larga pisaba el texto de la derecha.
pdf_linea($pdf, $m, PDF_ALTO - 62, $der, PDF_ALTO - 62, '#d8dee6');
pdf_texto($pdf, $m, PDF_ALTO - 46, 'Cotización de referencia generada en ' . $sitio . ' con los precios vigentes el ' . $larga($hoy) . '.', 8, false, '#56616e');
pdf_texto($pdf, $m, PDF_ALTO - 33, 'VCodePro · Berlín, Alemania · licencias@vcodepro.de', 8, false, '#56616e');

$documento = pdf_salida($pdf);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="cotizacion-vcodepro-' . $numero . '.pdf"');
header('Content-Length: ' . strlen($documento));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $documento;
