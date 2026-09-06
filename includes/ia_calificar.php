<?php
/**
 * Calificación asistida por IA de una entrega.
 *
 * Reúne todo lo que el docente miraría —la actividad, su rúbrica, lo que el
 * estudiante escribió en cada fase, su descripción de la solución, la
 * declaración de uso de IA y los adjuntos de texto— y le pide al modelo un
 * puntaje y un comentario por cada criterio, más una retroalimentación general.
 *
 * Lo que se guarda es una **propuesta**: las calificaciones quedan con
 * `origen = 'ia'`, la retroalimentación entra como comentario privado y la
 * entrega **no cambia de estado**, así que el estudiante no ve nada hasta que
 * el docente revisa y publica. La nota la pone una persona; esto solo le
 * ahorra el primer borrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/ia.php';
require_once __DIR__ . '/academico.php';
require_once __DIR__ . '/adjuntos.php';
require_once __DIR__ . '/richtext.php';

/** Adjuntos cuyo contenido tiene sentido leer como texto. */
const IA_EXT_LEGIBLES = ['py', 'txt', 'md', 'csv', 'tsv', 'json', 'ipynb', 'sql',
                         'java', 'c', 'cpp', 'h', 'html', 'css', 'xml', 'yml', 'yaml'];
const IA_MAX_ADJUNTO  = 30000;   // caracteres por archivo
const IA_MAX_TOTAL    = 120000;  // caracteres de adjuntos en total

/** El papel que asume el modelo. Se mantiene corto y muy concreto. */
function ia_sistema_calificar(): string {
    return <<<'TXT'
Eres un docente experimentado del Bachillerato Internacional que califica trabajos de
tecnología y diseño. Evalúas con la rúbrica que te dan, criterio por criterio, y escribes
en español de Colombia, dirigiéndote al estudiante de tú.

Cómo calificas:
- Solo puntúas lo que puedas sustentar con evidencia del trabajo entregado. Si no hay
  evidencia de un criterio, el puntaje es bajo y lo dices sin rodeos.
- Te ciñes a los descriptores: el puntaje es el del descriptor que mejor describe el
  trabajo, no una impresión general.
- No inventas lo que el estudiante no escribió, ni asumes intenciones.
- El comentario de cada criterio dice qué logró, qué falta y cuál es el siguiente paso
  concreto. Nada de elogios vacíos ni de reproches.
- Un trabajo vacío o casi vacío recibe los puntajes mínimos; no lo compensas.
- Si el estudiante no declaró el uso de IA y el texto lo sugiere, lo señalas en la
  retroalimentación como algo que conversar, nunca como una acusación.
TXT;
}

/** Esquema de la respuesta: nada de interpretar prosa para sacar un número. */
function ia_esquema_calificar(array $criterios): array {
    return [
        'type'       => 'object',
        'properties' => [
            'criterios' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'criterio'   => ['type' => 'string', 'description' => 'Letra del criterio: A, B, C, D o E'],
                        'puntaje'    => ['type' => 'integer'],
                        'comentario' => ['type' => 'string'],
                    ],
                    'required' => ['criterio', 'puntaje', 'comentario'],
                ],
            ],
            'retroalimentacion' => ['type' => 'string',
                'description' => 'Para el estudiante: qué logró, qué mejorar y el siguiente paso. Máximo 200 palabras.'],
            'senal_probidad' => ['type' => 'string',
                'description' => 'Vacío si no hay nada que señalar sobre la declaración de uso de IA.'],
        ],
        'required' => ['criterios', 'retroalimentacion'],
    ];
}

/** Texto de un adjunto, si es legible y no es enorme. */
function ia_texto_adjunto(array $a): ?string {
    $ext = strtolower((string) $a['extension']);
    if (!in_array($ext, IA_EXT_LEGIBLES, true)) return null;
    if ((int) $a['bytes'] > IA_MAX_ADJUNTO * 4) return null;

    if ($a['almacen'] === 's3') {
        $url = adjunto_url($a, 120);
        if ($url === '') return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $contenido = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($codigo !== 200 || !is_string($contenido)) return null;
    } else {
        $ruta = RUTA_SUBIDAS . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $a['clave']);
        if (!is_readable($ruta)) return null;
        $contenido = (string) file_get_contents($ruta);
    }
    $contenido = utf8_limpio($contenido);
    return mb_substr($contenido, 0, IA_MAX_ADJUNTO);
}

/**
 * Arma el expediente que se le entrega al modelo.
 * Devuelve el texto del prompt y, aparte, si la entrega venía vacía.
 */
function ia_expediente(array $e, array $act, array $fases, array $adjuntos): array {
    $p = [];
    $p[] = "## Actividad\n";
    $p[] = "Título: {$act['titulo']} ({$act['codigo']})";
    $p[] = "Nivel: {$act['grado']} · {$act['programa_ib']}";
    $p[] = "Pregunta de indagación: {$act['pregunta_indagacion']}";
    $p[] = "Descripción: " . rico_plano($act['descripcion']);
    if (trim((string) $act['objetivos']) !== '')   $p[] = "Objetivos:\n" . $act['objetivos'];
    if (trim((string) $act['entregables']) !== '') $p[] = "Entregables esperados:\n" . $act['entregables'];
    if (trim((string) ($e['instrucciones'] ?? '')) !== '') {
        $p[] = "Indicaciones que dio el docente al asignarla: " . rico_plano($e['instrucciones']);
    }

    $p[] = "\n## Rúbrica\n";
    foreach ($act['rubrica'] as $c) {
        $p[] = "### Criterio {$c['criterio']} — {$c['nombre']} (máximo {$c['maximo']} puntos)";
        $p[] = "- 7-8 (o el tramo alto): {$c['descriptor_78']}";
        $p[] = "- 5-6: {$c['descriptor_56']}";
        $p[] = "- 3-4: {$c['descriptor_34']}";
        $p[] = "- 1-2: {$c['descriptor_12']}";
        $p[] = "- 0: no hay evidencia de este criterio.";
    }

    $p[] = "\n## Lo que entregó el estudiante\n";
    $p[] = "Intento número: " . (int) $e['intento'] . ". Avance declarado: " . (int) $e['progreso'] . "%.";

    $hayContenido = false;
    $p[] = "\n### Trabajo por fases del ciclo de diseño";
    foreach ($fases as $f) {
        $texto = rico_plano($f['contenido'] ?? '');
        $marca = $f['completada'] ? 'marcada como completada' : 'sin marcar';
        if ($texto === '') {
            $p[] = "- [{$f['fase']}] {$f['titulo']}: SIN NADA ESCRITO ($marca).";
        } else {
            $hayContenido = true;
            $p[] = "- [{$f['fase']}] {$f['titulo']} ($marca):\n{$texto}";
        }
    }

    $desc = rico_plano($e['texto'] ?? '');
    $p[] = "\n### Descripción de la solución\n" . ($desc !== '' ? $desc : 'No escribió ninguna.');
    if ($desc !== '') $hayContenido = true;

    $ia = rico_plano($e['uso_ia'] ?? '');
    $p[] = "\n### Declaración de uso de inteligencia artificial\n"
         . ($ia !== '' ? $ia : 'No declaró nada. La actividad exige declararlo.');

    if (trim((string) $e['url_repo']) !== '') {
        $p[] = "\n### Enlace al producto\n{$e['url_repo']} (no puedes abrirlo; tenlo en cuenta solo como indicio de que existe).";
    }

    if ($adjuntos) {
        $p[] = "\n### Archivos adjuntos";
        $total = 0;
        foreach ($adjuntos as $a) {
            $texto = $total < IA_MAX_TOTAL ? ia_texto_adjunto($a) : null;
            if ($texto !== null && trim($texto) !== '') {
                $total += mb_strlen($texto);
                $hayContenido = true;
                $p[] = "\n#### {$a['nombre']} (" . adjunto_peso((int) $a['bytes']) . ")\n```\n{$texto}\n```";
            } else {
                $p[] = "- {$a['nombre']} (" . adjunto_peso((int) $a['bytes'])
                     . ") — no puedes leer su contenido; cuenta solo como evidencia de que lo entregó.";
            }
        }
    }

    $p[] = "\n## Tu tarea\n";
    $p[] = "Califica cada criterio de la rúbrica de arriba con su puntaje y su comentario, y escribe "
         . "la retroalimentación general. Usa exactamente las letras de criterio que aparecen en la rúbrica "
         . "y no te salgas del máximo de cada uno.";
    if (!$hayContenido) {
        $p[] = "AVISO: esta entrega está prácticamente vacía. Puntúa en consecuencia y dile al "
             . "estudiante qué tendría que haber entregado.";
    }

    return [implode("\n", $p), $hayContenido];
}

/**
 * Califica una entrega con el asistente y guarda la propuesta.
 * Devuelve [true, $resumen] o [false, 'motivo'].
 */
function ia_calificar_entrega(int $entregaId, array $docente): array {
    $e = fila('SELECT e.*, a.instrucciones, a.actividad_id, a.docente_id, a.estado AS estado_asignacion,
                      CONCAT(u.nombre, " ", u.apellidos) AS estudiante
                 FROM entregas e
                 JOIN asignaciones a ON a.id = e.asignacion_id
                 JOIN usuarios u ON u.id = e.estudiante_id
                WHERE e.id = ?', [$entregaId]);
    if (!$e) return [false, 'No encontramos esa entrega.'];
    if (!es('admin') && (int) $e['docente_id'] !== (int) $docente['id']) {
        return [false, 'Esa entrega no es de un grupo tuyo.'];
    }

    $act = actividad_completa((int) $e['actividad_id']);
    if (!$act || !$act['rubrica']) return [false, 'La actividad no tiene rúbrica que aplicar.'];

    $fases = filas('SELECT af.fase, af.titulo, ef.contenido, ef.completada
                      FROM actividad_fases af
                 LEFT JOIN entrega_fases ef ON ef.fase_id = af.id AND ef.entrega_id = ?
                     WHERE af.actividad_id = ? ORDER BY af.orden', [$entregaId, $e['actividad_id']]);
    $adjuntos = adjuntos_de($entregaId);

    [$prompt] = ia_expediente($e, $act, $fases, $adjuntos);

    [$ok, $r] = ia_generar(
        ia_sistema_calificar(),
        $prompt,
        ia_esquema_calificar($act['rubrica']),
        ['accion' => 'calificar', 'entrega_id' => $entregaId, 'timeout' => 240]
    );
    if (!$ok) return [false, $r];

    // --- Se valida antes de guardar: la IA propone, el esquema no garantiza ---
    $porLetra = [];
    foreach ($act['rubrica'] as $c) $porLetra[strtoupper((string) $c['criterio'])] = $c;

    $guardadas = 0; $obtenido = 0; $maximo = 0;
    foreach ($act['rubrica'] as $c) $maximo += (int) $c['maximo'];

    foreach ($r['criterios'] ?? [] as $prop) {
        $letra = strtoupper(trim((string) ($prop['criterio'] ?? '')));
        if (!isset($porLetra[$letra])) continue;          // criterio inventado: se ignora
        $c = $porLetra[$letra];
        // El puntaje se recorta al rango real del criterio, pase lo que pase.
        $puntaje = max(0, min((int) $c['maximo'], (int) ($prop['puntaje'] ?? 0)));
        $comentario = rico_sanear(trim((string) ($prop['comentario'] ?? '')));

        $datos = [
            'puntaje'    => $puntaje,
            'comentario' => $comentario,
            'docente_id' => $docente['id'],
            'origen'     => 'ia',
            'fecha'      => date('Y-m-d H:i:s'),
        ];
        $ex = valor('SELECT id FROM calificaciones WHERE entrega_id = ? AND criterio_id = ?',
                    [$entregaId, $c['id']]);
        if ($ex) {
            actualizar('calificaciones', $datos, 'id = :__id', ['__id' => $ex]);
        } else {
            insertar('calificaciones', $datos + ['entrega_id' => $entregaId, 'criterio_id' => (int) $c['id']]);
        }
        $obtenido += $puntaje;
        $guardadas++;
    }

    if (!$guardadas) return [false, 'La IA no devolvió ningún criterio reconocible.'];

    // La retroalimentación entra como comentario privado: la ve el docente, no
    // el estudiante, hasta que se publique.
    $retro = trim((string) ($r['retroalimentacion'] ?? ''));
    $senal = trim((string) ($r['senal_probidad'] ?? ''));
    if ($senal !== '') $retro .= "\n\nSobre la declaración de uso de IA: " . $senal;
    if ($retro !== '') {
        borrar('comentarios', 'entrega_id = ? AND autor_id = ? AND privado = 1 AND mensaje LIKE ?',
               [$entregaId, $docente['id'], '%<!--ia-->%']);
        insertar('comentarios', [
            'entrega_id' => $entregaId,
            'autor_id'   => $docente['id'],
            'mensaje'    => '<!--ia-->' . rico_sanear($retro),
            'privado'    => 1,
        ]);
    }

    actualizar('entregas', ['ia_calificada_en' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $entregaId]);
    auditar('ia_calificacion_propuesta', 'entregas', $entregaId,
            $e['estudiante'] . ' · ' . $obtenido . '/' . $maximo);

    return [true, [
        'entrega'    => $entregaId,
        'estudiante' => $e['estudiante'],
        'obtenido'   => $obtenido,
        'maximo'     => $maximo,
        'criterios'  => $guardadas,
        'nota'       => nota_ib((float) $obtenido, (float) $maximo)[0],
    ]];
}

/** Retroalimentación que dejó el asistente, si la hay. */
function ia_retro_de(int $entregaId): ?array {
    $c = fila('SELECT * FROM comentarios WHERE entrega_id = ? AND privado = 1 AND mensaje LIKE ? ORDER BY id DESC LIMIT 1',
              [$entregaId, '%<!--ia-->%']);
    if (!$c) return null;
    $c['mensaje'] = str_replace('<!--ia-->', '', (string) $c['mensaje']);
    return $c;
}

/** Entregas de una asignación que el asistente puede calificar. */
function ia_entregas_de(int $asignacionId): array {
    return filas('SELECT e.id, e.estado, e.progreso, e.ia_calificada_en,
                         CONCAT(u.apellidos, ", ", u.nombre) AS estudiante
                    FROM entregas e JOIN usuarios u ON u.id = e.estudiante_id
                   WHERE e.asignacion_id = ?
                ORDER BY u.apellidos, u.nombre', [$asignacionId]);
}
