<?php
/**
 * Borrador de una actividad nueva, redactado por el asistente.
 *
 * Devuelve los campos del formulario ya rellenos —incluidas las cuatro fases
 * del ciclo de diseño— para que el docente los revise, los ajuste y guarde.
 * No escribe nada en la base de datos: quien crea la actividad es la persona.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/ia.php';
require_once __DIR__ . '/../../includes/academico.php';

header('X-Content-Type-Options: nosniff');

$u = usuario();
if (!$u)                          json_salida(['ok' => false, 'error' => 'Tu sesión caducó. Vuelve a entrar.'], 401);
if (!es_post() || !csrf_valido()) json_salida(['ok' => false, 'error' => 'Token inválido; recarga la página.'], 419);
if (!ia_permitida($u))            json_salida(['ok' => false, 'error' => 'No tienes habilitado el asistente de IA.'], 403);

$tema = trim((string) post('tema'));
if ($tema === '') json_salida(['ok' => false, 'error' => 'Dinos de qué trata la actividad.'], 400);

$nivel = fila('SELECT * FROM niveles WHERE id = ?', [post_int('nivel_id')])
      ?: fila('SELECT * FROM niveles ORDER BY grado LIMIT 1');
if (!$nivel) json_salida(['ok' => false, 'error' => 'No hay niveles configurados.'], 400);

$esDP = in_array($nivel['codigo'] ?? '', ['N11', 'N12'], true);

$sistema = <<<'TXT'
Eres coordinador de Diseño y Tecnología en un colegio del Bachillerato Internacional.
Escribes actividades para el aula: concretas, realizables en el tiempo indicado y con un
producto que el estudiante pueda mostrar. Escribes en español de Colombia.

Reglas:
- La actividad se articula sobre el ciclo de diseño del PAI: indagar y analizar, desarrollar
  ideas, crear la solución, evaluar. Una fase de cada una, en ese orden.
- Nada de relleno: cada instrucción dice qué hace el estudiante, no qué "comprenderá".
- La evidencia de cada fase es algo entregable y verificable.
- Los objetivos y los entregables son listas, una línea por elemento, sin viñetas ni números.
- La descripción va en HTML sencillo: solo <p>, <strong> y <code>.
TXT;

$rubricaCriterios = $esDP ? 'A a E del Programa del Diploma' : 'A, B, C y D del PAI';
$contextos = implode(' | ', CONTEXTOS_GLOBALES);
$perfil    = implode(' | ', PERFIL_IB);
$atl       = implode(' | ', ATL);

$prompt = <<<TXT
Redacta una actividad para {$nivel['grado']}.º ({$nivel['nombre']}, programa {$nivel['programa_ib']}).

Tema pedido por el docente: {$tema}

Elige el contexto global de esta lista exacta: {$contextos}
Elige un atributo del perfil de esta lista exacta: {$perfil}
Elige una habilidad ATL de esta lista exacta: {$atl}

La rúbrica tendrá los criterios {$rubricaCriterios}. Ajusta la exigencia al grado.
Devuelve exactamente cuatro fases, una por cada etapa del ciclo de diseño, con la etapa
escrita como indagar, desarrollar, crear o evaluar.
TXT;


$esquema = [
    'type' => 'object',
    'properties' => [
        'titulo'              => ['type' => 'string'],
        'resumen'             => ['type' => 'string', 'description' => 'Una frase, máximo 160 caracteres.'],
        'descripcion'         => ['type' => 'string', 'description' => 'HTML sencillo con <p> y <strong>. Dos o tres párrafos.'],
        'pregunta_indagacion' => ['type' => 'string'],
        'contexto_global'     => ['type' => 'string'],
        'concepto_clave'      => ['type' => 'string'],
        'perfil_ib'           => ['type' => 'string'],
        'atl'                 => ['type' => 'string'],
        'objetivos'           => ['type' => 'string', 'description' => 'Una línea por objetivo, separadas por saltos de línea.'],
        'entregables'         => ['type' => 'string', 'description' => 'Una línea por entregable.'],
        'lenguaje'            => ['type' => 'string', 'description' => 'Python, Scratch, JavaScript, micro:bit u otro.'],
        'dificultad'          => ['type' => 'string', 'description' => 'inicial, intermedio o avanzado'],
        'sesiones'            => ['type' => 'integer'],
        'horas'               => ['type' => 'number'],
        'codigo_inicial'      => ['type' => 'string', 'description' => 'Código de arranque, o cadena vacía.'],
        'fases' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'fase'          => ['type' => 'string', 'description' => 'indagar, desarrollar, crear o evaluar'],
                    'titulo'        => ['type' => 'string'],
                    'instrucciones' => ['type' => 'string'],
                    'entregable'    => ['type' => 'string'],
                    'minutos'       => ['type' => 'integer'],
                ],
                'required' => ['fase', 'titulo', 'instrucciones', 'entregable', 'minutos'],
            ],
        ],
    ],
    'required' => ['titulo', 'resumen', 'descripcion', 'pregunta_indagacion', 'contexto_global',
                   'objetivos', 'entregables', 'lenguaje', 'dificultad', 'sesiones', 'horas', 'fases'],
];

[$ok, $r] = ia_generar($sistema, $prompt, $esquema, ['accion' => 'redactar_actividad', 'timeout' => 240]);
if (!$ok) json_salida(['ok' => false, 'error' => $r], 422);

// --- Se normaliza lo que llega: el modelo propone, el portal decide -------
$normalizar = function (string $valor, array $permitidos): string {
    foreach ($permitidos as $p) {
        if (mb_strtolower(trim($valor)) === mb_strtolower($p)) return $p;
    }
    // Si no coincide exactamente, se busca la opción más parecida.
    foreach ($permitidos as $p) {
        if (str_contains(mb_strtolower($p), mb_strtolower(trim($valor)))) return $p;
    }
    return $permitidos[0];
};

$fasesValidas = array_keys(FASES_CICLO);
$fases = [];
foreach ($r['fases'] ?? [] as $f) {
    $etapa = mb_strtolower(trim((string) ($f['fase'] ?? '')));
    if (!in_array($etapa, $fasesValidas, true)) continue;
    $fases[] = [
        'fase'          => $etapa,
        'titulo'        => corte(utf8_limpio((string) $f['titulo']), 200),
        'instrucciones' => utf8_limpio((string) $f['instrucciones']),
        'entregable'    => corte(utf8_limpio((string) $f['entregable']), 300),
        'minutos'       => max(5, min(600, (int) $f['minutos'])),
    ];
}

$actividad = [
    'titulo'              => corte(utf8_limpio((string) $r['titulo']), 200),
    'resumen'             => corte(utf8_limpio((string) $r['resumen']), 300),
    'descripcion'         => (string) $r['descripcion'],
    'pregunta_indagacion' => corte(utf8_limpio((string) $r['pregunta_indagacion']), 300),
    'contexto_global'     => $normalizar((string) ($r['contexto_global'] ?? ''), CONTEXTOS_GLOBALES),
    'concepto_clave'      => corte(utf8_limpio((string) ($r['concepto_clave'] ?? '')), 120),
    'perfil_ib'           => $normalizar((string) ($r['perfil_ib'] ?? ''), PERFIL_IB),
    'atl'                 => $normalizar((string) ($r['atl'] ?? ''), ATL),
    'objetivos'           => utf8_limpio((string) $r['objetivos']),
    'entregables'         => utf8_limpio((string) $r['entregables']),
    'lenguaje'            => corte(utf8_limpio((string) $r['lenguaje']), 60),
    'dificultad'          => in_array($r['dificultad'] ?? '', ['inicial', 'intermedio', 'avanzado'], true)
                             ? $r['dificultad'] : 'inicial',
    'sesiones'            => max(1, min(40, (int) $r['sesiones'])),
    'horas'               => max(0.5, min(80, (float) $r['horas'])),
    'codigo_inicial'      => utf8_limpio((string) ($r['codigo_inicial'] ?? '')),
    'fases'               => $fases,
];

json_salida(['ok' => true, 'actividad' => $actividad]);
