<?php
/**
 * Asistente de IA del portal (API de Google Gemini).
 *
 * Sin SDK ni Composer: una petición HTTP con cURL y JSON, como el resto de
 * integraciones del proyecto.
 *
 * Dos ideas que conviene no perder de vista:
 *
 * 1. **Lo que devuelve la IA es una propuesta, no una nota.** El docente es
 *    quien califica; el asistente le ahorra el primer borrador. Por eso cada
 *    calificación se guarda con `origen = 'ia'` y la entrega no se publica
 *    sola: el docente la revisa y la publica. Es su firma la que va en el
 *    boletín, y en el IB la responsabilidad de la evaluación es del docente.
 * 2. **Se pide la respuesta como JSON con esquema.** Nada de interpretar prosa
 *    para sacar un número: el modelo devuelve la estructura exacta y, aun así,
 *    los puntajes se recortan al máximo de cada criterio antes de guardarlos.
 *
 * La clave nunca se escribe aquí: viene de includes/config.local.php, de una
 * variable de entorno o de los ajustes del portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** Modelo por defecto: rápido y con contexto de sobra para una entrega larga. */
const IA_MODELO_POR_DEFECTO = 'gemini-3.8-flash';

/** Modelos que el administrador puede elegir, con su porqué. */
const IA_MODELOS = [
    'gemini-3.8-flash'     => 'Rápido y suficiente para calificar (recomendado)',
    'gemini-3.5-flash'     => 'Alternativa estable si el anterior da problemas',
    'gemini-3.1-pro-preview' => 'Más cuidadoso, unas tres veces más lento',
    'gemini-2.5-flash'     => 'Generación anterior, la más barata',
];

function ia_dato(string $constante, string $entorno, string $clave, string $porDefecto = ''): string {
    if (defined($constante) && constant($constante) !== '') return (string) constant($constante);
    $env = getenv($entorno);
    if ($env !== false && $env !== '') return $env;
    return (string) ajuste($clave, $porDefecto);
}

function ia_clave(): string  { return ia_dato('IA_CLAVE',  'VCP_IA_CLAVE',  'ia_clave'); }
function ia_modelo(): string {
    $m = ia_dato('IA_MODELO', 'VCP_IA_MODELO', 'ia_modelo', IA_MODELO_POR_DEFECTO);
    return array_key_exists($m, IA_MODELOS) ? $m : IA_MODELO_POR_DEFECTO;
}
function ia_configurada(): bool { return ia_clave() !== ''; }

/** Últimos caracteres de la clave, para mostrarla sin exponerla. */
function ia_pista(string $valor): string {
    return $valor === '' ? '' : '…' . substr($valor, -4);
}

/**
 * ¿Este usuario puede usar el asistente?
 *
 * La administración lo habilita docente por docente. No basta con tener la
 * clave configurada: el permiso es individual y revocable.
 */
function ia_permitida(?array $u = null): bool {
    $u ??= usuario();
    if (!$u || !ia_configurada()) return false;
    if ($u['rol'] === 'admin') return true;
    return $u['rol'] === 'docente' && !empty($u['ia_habilitada']);
}

/**
 * Llama al modelo y devuelve [true, $datos] o [false, 'motivo'].
 *
 * @param string     $sistema  Instrucción de sistema (el papel que asume).
 * @param string     $prompt   La petición concreta.
 * @param array|null $esquema  Esquema JSON de la respuesta; si se pasa, el
 *                             resultado llega ya decodificado como arreglo.
 */
function ia_generar(string $sistema, string $prompt, ?array $esquema = null, array $opciones = []): array {
    if (!ia_configurada()) return [false, 'El asistente de IA no está configurado.'];

    $cuerpo = [
        'systemInstruction' => ['parts' => [['text' => $sistema]]],
        'contents'          => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig'  => [
            // Temperatura baja: al calificar interesa que dos ejecuciones sobre
            // el mismo trabajo den prácticamente lo mismo.
            'temperature'     => $opciones['temperatura'] ?? 0.2,
            'maxOutputTokens' => $opciones['max_tokens'] ?? 16384,
        ],
        // Seguridad: son trabajos escolares; no queremos que el modelo se
        // niegue a calificar un texto sobre, por ejemplo, ciberacoso.
        'safetySettings' => array_map(fn($c) => ['category' => $c, 'threshold' => 'BLOCK_ONLY_HIGH'], [
            'HARM_CATEGORY_HARASSMENT', 'HARM_CATEGORY_HATE_SPEECH',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'HARM_CATEGORY_DANGEROUS_CONTENT',
        ]),
    ];
    if ($esquema) {
        $cuerpo['generationConfig']['responseMimeType'] = 'application/json';
        $cuerpo['generationConfig']['responseSchema']   = $esquema;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode(ia_modelo()) . ':generateContent';

    $inicio = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . ia_clave()],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ($opciones['timeout'] ?? 180),
    ]);
    $respuesta = curl_exec($ch);
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errCurl   = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $inicio) * 1000);

    if ($errCurl !== '') {
        ia_registrar($opciones, 0, $ms, false, 'conexión: ' . $errCurl);
        return [false, 'No se pudo conectar con el servicio de IA: ' . $errCurl];
    }

    $d = json_decode((string) $respuesta, true);
    if (!is_array($d)) {
        ia_registrar($opciones, 0, $ms, false, "respuesta ilegible ($codigo)");
        return [false, 'El servicio de IA devolvió una respuesta que no se pudo leer.'];
    }
    if (isset($d['error'])) {
        $motivo = ia_motivo($codigo, (string) ($d['error']['message'] ?? ''));
        ia_registrar($opciones, 0, $ms, false, corte($motivo, 200));
        return [false, $motivo];
    }

    $tokens = (int) ($d['usageMetadata']['totalTokenCount'] ?? 0);
    $cand   = $d['candidates'][0] ?? null;
    $razon  = $cand['finishReason'] ?? '';

    if ($razon === 'SAFETY' || $razon === 'PROHIBITED_CONTENT') {
        ia_registrar($opciones, $tokens, $ms, false, 'contenido bloqueado');
        return [false, 'El servicio de IA se negó a procesar este contenido. Revísalo a mano.'];
    }

    $texto = '';
    foreach ($cand['content']['parts'] ?? [] as $p) $texto .= $p['text'] ?? '';
    if (trim($texto) === '') {
        $nota = $razon === 'MAX_TOKENS'
            ? 'La respuesta se cortó por longitud. Prueba con una entrega más corta.'
            : 'El servicio de IA devolvió una respuesta vacía.';
        ia_registrar($opciones, $tokens, $ms, false, corte($nota, 200));
        return [false, $nota];
    }

    ia_registrar($opciones, $tokens, $ms, true, null);

    if (!$esquema) return [true, $texto];

    $json = json_decode($texto, true);
    if (!is_array($json)) return [false, 'La respuesta de la IA no tenía el formato esperado.'];
    return [true, $json];
}

/** Traduce el error de la API a algo accionable para un docente. */
function ia_motivo(int $codigo, string $mensaje): string {
    return match (true) {
        $codigo === 400 && str_contains($mensaje, 'API key')
            => 'La clave del asistente de IA no es válida.',
        $codigo === 403 => 'La clave del asistente de IA no tiene permiso para este modelo.',
        $codigo === 429 => 'Se alcanzó el límite de peticiones de la IA. Espera un momento y reintenta.',
        $codigo >= 500  => 'El servicio de IA no está disponible ahora mismo. Reintenta en unos minutos.',
        default         => 'El servicio de IA respondió con un error' . ($mensaje !== '' ? ': ' . corte($mensaje, 180) : '.'),
    };
}

/** Deja constancia de cada llamada: gasto, latencia y a cuenta de quién. */
function ia_registrar(array $opciones, int $tokens, int $ms, bool $ok, ?string $detalle): void {
    insertar('ia_registros', [
        'usuario_id' => uid() ?: null,
        'accion'     => $opciones['accion'] ?? 'generar',
        'entrega_id' => $opciones['entrega_id'] ?? null,
        'modelo'     => ia_modelo(),
        'tokens'     => $tokens,
        'ms'         => $ms,
        'ok'         => $ok ? 1 : 0,
        'detalle'    => $detalle,
    ]);
}

/** Diagnóstico para el panel de ajustes. */
function ia_probar(): array {
    if (!ia_configurada()) return [false, 'Falta la clave del asistente.'];
    [$ok, $r] = ia_generar(
        'Responde solo con el JSON pedido.',
        'Devuelve {"saludo":"listo"} exactamente.',
        ['type' => 'object', 'properties' => ['saludo' => ['type' => 'string']], 'required' => ['saludo']],
        ['accion' => 'diagnostico', 'timeout' => 60]
    );
    if (!$ok) return [false, $r];
    return [true, 'Conexión correcta con ' . ia_modelo() . '.'];
}

/** Consumo del mes, para que la administración vea el gasto de un vistazo. */
function ia_consumo_mes(): array {
    return fila("SELECT COUNT(*) AS llamadas, COALESCE(SUM(tokens),0) AS tokens,
                        SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) AS fallos
                   FROM ia_registros
                  WHERE creado_en >= DATE_FORMAT(NOW(), '%Y-%m-01')") ?: [];
}
