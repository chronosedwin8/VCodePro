<?php
/**
 * Saneado del texto enriquecido que escriben los estudiantes.
 *
 * El editor del portal produce HTML, y ese HTML lo lee después el docente al
 * calificar. Sin saneado, un estudiante podría guardar un script y ejecutarlo
 * en la sesión de su profesor: sería un XSS almacenado con escalada de
 * privilegios. Por eso todo pasa por una lista blanca, dos veces: al guardar y
 * al mostrar.
 *
 * La política es deliberadamente estrecha. No se permiten imágenes, tablas,
 * estilos ni atributos de clase: solo lo que un estudiante necesita para
 * redactar una respuesta.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Etiquetas permitidas y, para cada una, sus atributos admitidos. */
const RICO_PERMITIDO = [
    'p'          => [],
    'br'         => [],
    'strong'     => [],
    'em'         => [],
    'u'          => [],
    's'          => [],
    'ul'         => [],
    'ol'         => [],
    'li'         => [],
    'h3'         => [],
    'h4'         => [],
    'blockquote' => [],
    'code'       => [],
    'pre'        => [],
    'a'          => ['href'],
];

/**
 * Etiquetas que se borran con todo su contenido.
 * En el resto basta con quitar la etiqueta y conservar el texto, pero el
 * interior de un <script> o un <style> no es prosa: si se conserva, aparece
 * como basura en la pantalla del docente.
 */
const RICO_DESCARTAR = ["script", "style", "iframe", "object", "embed", "noscript",
                        "template", "head", "title", "link", "meta", "form", "svg", "math"];

/** Detecta si un contenido trae marcas nuestras o es texto plano heredado. */
const RICO_PATRON_ETIQUETA =
    '#</?(p|br|strong|em|u|s|ul|ol|li|h3|h4|blockquote|code|pre|a)\b[^>]*>#i';

/** Longitud máxima del HTML guardado, como freno a un pegado descomunal. */
const RICO_MAX_BYTES = 120000;

/**
 * Deja el HTML en la lista blanca: elimina etiquetas y atributos no admitidos,
 * y descarta cualquier enlace que no sea http, https o mailto.
 */
function rico_sanear(string $html): string {
    $html = utf8_limpio(trim($html));
    if ($html === '') return '';
    // mb_strcut, no substr: cortar a mitad de un carácter dejaría UTF-8 roto.
    if (strlen($html) > RICO_MAX_BYTES) $html = mb_strcut($html, 0, RICO_MAX_BYTES, 'UTF-8');

    // Solo se trata como HTML si trae alguna etiqueta de la lista blanca. Con
    // strip_tags() bastaba un «if (a < b)» de una respuesta antigua en texto
    // plano para que el fragmento se interpretara como marcas y se perdiera.
    if (!preg_match(RICO_PATRON_ETIQUETA, $html)) return rico_desde_texto($html);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $previo = libxml_use_internal_errors(true);
    // El prefijo fuerza la interpretación como UTF-8 sin añadir <html> propio.
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div id="vcp-raiz">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previo);

    $raiz = $doc->getElementById('vcp-raiz');
    if (!$raiz) return rico_desde_texto(strip_tags($html));

    rico_limpiar_nodo($raiz, $doc);

    $salida = '';
    foreach (iterator_to_array($raiz->childNodes) as $hijo) {
        $salida .= $doc->saveHTML($hijo);
    }

    $salida = trim($salida);
    // Un documento sin texto no se guarda: un <p><br></p> suelto haría que la
    // vista del docente creyera que hay respuesta y mostrara un bloque vacío.
    return trim(strip_tags($salida)) === '' ? '' : $salida;
}

/** Recorre el árbol quitando lo que no esté permitido. */
function rico_limpiar_nodo(DOMNode $nodo, DOMDocument $doc): void {
    foreach (iterator_to_array($nodo->childNodes) as $hijo) {
        if ($hijo instanceof DOMText) continue;

        if ($hijo instanceof DOMComment || !($hijo instanceof DOMElement)) {
            $hijo->parentNode?->removeChild($hijo);
            continue;
        }

        $etiqueta = strtolower($hijo->nodeName);

        if (in_array($etiqueta, RICO_DESCARTAR, true)) {
            $hijo->parentNode?->removeChild($hijo);
            continue;
        }

        if (!array_key_exists($etiqueta, RICO_PERMITIDO)) {
            // La etiqueta se descarta, pero su texto se conserva.
            rico_limpiar_nodo($hijo, $doc);
            $padre = $hijo->parentNode;
            while ($hijo->firstChild) {
                $padre?->insertBefore($hijo->firstChild, $hijo);
            }
            $padre?->removeChild($hijo);
            continue;
        }

        foreach (iterator_to_array($hijo->attributes) as $attr) {
            $nombre = strtolower($attr->nodeName);
            if (!in_array($nombre, RICO_PERMITIDO[$etiqueta], true)) {
                $hijo->removeAttribute($attr->nodeName);
                continue;
            }
            if ($nombre === 'href' && !rico_enlace_seguro($attr->nodeValue ?? '')) {
                $hijo->removeAttribute('href');
            }
        }

        if ($etiqueta === 'a') {
            if ($hijo->hasAttribute('href')) {
                // Los enlaces que sobreviven se abren fuera y sin pasar referente.
                $hijo->setAttribute('rel', 'noopener nofollow');
                $hijo->setAttribute('target', '_blank');
            } else {
                // Un ancla sin destino no es un enlace: queda solo su texto.
                rico_limpiar_nodo($hijo, $doc);
                $padre = $hijo->parentNode;
                while ($hijo->firstChild) { $padre?->insertBefore($hijo->firstChild, $hijo); }
                $padre?->removeChild($hijo);
                continue;
            }
        }

        rico_limpiar_nodo($hijo, $doc);
    }
}

/** Solo http, https y mailto; nada de javascript: ni data:. */
function rico_enlace_seguro(string $url): bool {
    $url = trim($url);
    if ($url === '') return false;
    // Se normalizan los espacios en blanco que sirven para disfrazar el esquema.
    $limpia = strtolower(preg_replace('/[\s\x00-\x1F]+/', '', $url) ?? '');
    foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $malo) {
        if (str_starts_with($limpia, $malo)) return false;
    }
    return (bool) preg_match('#^(https?://|mailto:|/)#i', $limpia);
}

/** Convierte texto plano en párrafos, respetando las líneas en blanco. */
function rico_desde_texto(string $texto): string {
    $texto = trim($texto);
    if ($texto === '') return '';
    $bloques = preg_split('/\R{2,}/', $texto) ?: [$texto];
    $out = '';
    foreach ($bloques as $b) {
        $b = trim($b);
        if ($b === '') continue;
        $out .= '<p>' . nl2br(h($b), false) . '</p>';
    }
    return $out;
}

/**
 * Muestra contenido escrito por un estudiante.
 * Sanea otra vez: lo guardado hace meses pudo entrar con una política distinta.
 */
function rico_mostrar(?string $contenido): string {
    $contenido = (string) $contenido;
    if (trim($contenido) === '') return '';
    return rico_sanear($contenido);
}

/** Versión en texto plano, para resúmenes, tarjetas y exportaciones. */
function rico_plano(?string $contenido, int $limite = 0): string {
    $texto = trim(html_entity_decode(strip_tags(
        str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], ' ', (string) $contenido)
    ), ENT_QUOTES, 'UTF-8'));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    return $limite > 0 ? corte($texto, $limite) : $texto;
}

/** Cuenta las palabras reales de un contenido enriquecido. */
function rico_palabras(?string $contenido): int {
    $texto = rico_plano($contenido);
    return $texto === '' ? 0 : count(preg_split('/\s+/u', $texto) ?: []);
}

/**
 * Bloque listo para pintar contenido de un estudiante en cualquier pantalla.
 * Devuelve cadena vacía si no hay nada, para que la vista decida qué mostrar.
 */
function bloque_rico(?string $contenido, string $clase = ''): string {
    $html = rico_mostrar($contenido);
    if ($html === '') return '';
    return '<div class="rico-lectura' . ($clase !== '' ? ' ' . h($clase) : '') . '">' . $html . '</div>';
}
/**
 * Lee un campo del formulario que viene del editor enriquecido.
 * Es el único camino por el que debe entrar ese HTML a la base de datos.
 */
function post_rico(string $campo): string {
    return rico_sanear((string) post($campo));
}
