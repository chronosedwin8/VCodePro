<?php
/**
 * Generador mínimo de PDF, sin dependencias.
 *
 * Una página A4 con texto en Helvetica, rectángulos de color y líneas: lo
 * justo para documentos como la cotización. No embebe fuentes: usa las 14
 * tipografías estándar que todo lector de PDF trae, con codificación
 * WinAnsi, que cubre las tildes, la eñe, «», · y —.
 *
 * Las coordenadas se dan desde la esquina superior izquierda, en puntos
 * (A4 = 595 × 842), y aquí se convierten al origen inferior del PDF.
 *
 *   $pdf = pdf_nuevo('Título');
 *   pdf_texto($pdf, 56, 80, 'Hola', 12, true);
 *   header('Content-Type: application/pdf'); echo pdf_salida($pdf);
 */

declare(strict_types=1);

const PDF_ANCHO = 595.28;
const PDF_ALTO  = 841.89;

/** Anchos de Helvetica (milésimas de em) para alinear y partir líneas. */
const PDF_METRICAS = [
    ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
    '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
    '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
    '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
    '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
    'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
    'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
    'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, ']' => 278, '_' => 556, 'a' => 556, 'b' => 556,
    'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222,
    'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333,
    's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
    '·' => 278, '—' => 1000, '«' => 556, '»' => 556, '¿' => 611, '¡' => 333, '°' => 400, 'º' => 365,
];

function pdf_nuevo(string $titulo = ''): array {
    return ['titulo' => $titulo, 'ops' => []];
}

/** Color hexadecimal «#0078d4» a componentes de 0 a 1. */
function pdf_color(string $hex): string {
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = array_map(fn($p) => hexdec($p) / 255, str_split($hex, 2));
    return sprintf('%.3F %.3F %.3F', $r, $g, $b);
}

/** Texto UTF-8 a cadena literal de PDF en WinAnsi, con los caracteres especiales escapados. */
function pdf_cadena(string $texto): string {
    $w = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    return '(' . strtr($w, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']) . ')';
}

/** Ancho aproximado de un texto en puntos. */
function pdf_ancho(string $texto, float $tam, bool $negrita = false): float {
    $base = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
             'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N'];
    $total = 0;
    foreach (mb_str_split($texto) as $c) {
        $total += PDF_METRICAS[$base[$c] ?? $c] ?? 556;
    }
    return $total / 1000 * $tam * ($negrita ? 1.06 : 1);
}

function pdf_texto(array &$pdf, float $x, float $y, string $texto, float $tam = 10, bool $negrita = false,
                   string $color = '#1f2328', string $alinear = 'izq'): void {
    if ($alinear === 'der')    $x -= pdf_ancho($texto, $tam, $negrita);
    if ($alinear === 'centro') $x -= pdf_ancho($texto, $tam, $negrita) / 2;
    $pdf['ops'][] = sprintf('BT /%s %.2F Tf %s rg %.2F %.2F Td %s Tj ET',
        $negrita ? 'F2' : 'F1', $tam, pdf_color($color), $x, PDF_ALTO - $y, pdf_cadena($texto));
}

function pdf_rect(array &$pdf, float $x, float $y, float $ancho, float $alto, string $color): void {
    $pdf['ops'][] = sprintf('%s rg %.2F %.2F %.2F %.2F re f',
        pdf_color($color), $x, PDF_ALTO - $y - $alto, $ancho, $alto);
}

function pdf_linea(array &$pdf, float $x1, float $y1, float $x2, float $y2, string $color = '#d8dee6', float $grosor = .6): void {
    $pdf['ops'][] = sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S',
        pdf_color($color), $grosor, $x1, PDF_ALTO - $y1, $x2, PDF_ALTO - $y2);
}

/**
 * Párrafo con ajuste de línea al ancho dado. Devuelve la «y» siguiente, para
 * seguir escribiendo debajo.
 */
function pdf_parrafo(array &$pdf, float $x, float $y, float $ancho, string $texto, float $tam = 10,
                     bool $negrita = false, string $color = '#1f2328', float $interlineado = 1.45): float {
    $linea = '';
    foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $palabra) {
        $prueba = $linea === '' ? $palabra : $linea . ' ' . $palabra;
        if ($linea !== '' && pdf_ancho($prueba, $tam, $negrita) > $ancho) {
            pdf_texto($pdf, $x, $y, $linea, $tam, $negrita, $color);
            $y += $tam * $interlineado;
            $linea = $palabra;
        } else {
            $linea = $prueba;
        }
    }
    if ($linea !== '') {
        pdf_texto($pdf, $x, $y, $linea, $tam, $negrita, $color);
        $y += $tam * $interlineado;
    }
    return $y;
}

/** Documento completo, listo para enviar al navegador. */
function pdf_salida(array $pdf): string {
    $contenido = implode("\n", $pdf['ops']);
    $comprimido = function_exists('gzcompress') ? gzcompress($contenido, 9) : false;

    $objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
              . '/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>', PDF_ANCHO, PDF_ALTO),
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        $comprimido !== false
            ? '<< /Length ' . strlen($comprimido) . " /Filter /FlateDecode >>\nstream\n" . $comprimido . "\nendstream"
            : '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream",
        '<< /Title ' . pdf_cadena($pdf['titulo']) . ' /Producer (VCodePro) /CreationDate (D:' . date('YmdHis') . ') >>',
    ];

    $salida = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $posiciones = [];
    foreach ($objetos as $i => $obj) {
        $posiciones[] = strlen($salida);
        $salida .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
    }
    $xref = strlen($salida);
    $salida .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach ($posiciones as $p) $salida .= sprintf("%010d 00000 n \n", $p);
    $salida .= 'trailer << /Size ' . (count($objetos) + 1) . " /Root 1 0 R /Info 7 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    return $salida;
}
