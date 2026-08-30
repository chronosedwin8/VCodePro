<?php
/**
 * Cliente de la API de Phidias del Colegio Alemán de Barranquilla.
 *
 * Solo se usa el consolidado de matrícula:
 *   GET /1/course/consolidate
 *
 * La respuesta viene anidada en tres capas:
 *   sección del colegio (KINDERGARTEN, PRIMARIA, SECUNDARIA)
 *     └── curso (KLASSE 1 … KLASSE 12)
 *           └── grupo (K10A, K10B, K10C…)  ← es el «curso» del estudiante
 *                 └── estudiantes
 *
 * Aquí se aplana a una lista de grupos con sus estudiantes ya normalizados.
 * La respuesta pesa unos 2 MB, así que se guarda en caché en disco.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const PHIDIAS_CACHE_MINUTOS = 180;

/** Ruta del archivo de caché. */
function phidias_archivo_cache(): string {
    return RUTA_SUBIDAS . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'phidias-consolidate.json';
}

/**
 * Configuración de la conexión. El orden de precedencia es:
 * constante de includes/config.local.php → variable de entorno → ajustes del portal.
 */
function phidias_url(): string {
    if (defined('PHIDIAS_URL') && PHIDIAS_URL !== '') return rtrim((string) PHIDIAS_URL, '/');
    $env = getenv('VCP_PHIDIAS_URL');
    if ($env) return rtrim($env, '/');
    return rtrim((string) ajuste('phidias_url', 'https://ds-barranquilla.phidias.co/rest'), '/');
}

function phidias_token(): string {
    if (defined('PHIDIAS_TOKEN') && PHIDIAS_TOKEN !== '') return (string) PHIDIAS_TOKEN;
    $env = getenv('VCP_PHIDIAS_TOKEN');
    if ($env) return $env;
    return (string) ajuste('phidias_token', '');
}

function phidias_configurada(): bool {
    return phidias_token() !== '' && phidias_url() !== '';
}

/** Los últimos cuatro caracteres del token, para mostrar sin exponerlo. */
function phidias_token_pista(): string {
    $t = phidias_token();
    return $t === '' ? '' : '…' . substr($t, -6);
}

// ---------------------------------------------------------------- petición --

/**
 * Llama a un endpoint de la API y devuelve el JSON decodificado.
 * Devuelve [ok, datos|mensaje de error].
 */
function phidias_get(string $ruta, array $params = [], int $timeout = 60): array {
    if (!phidias_configurada()) {
        return [false, 'No hay token de Phidias configurado. Agrégalo en Ajustes o en includes/config.local.php.'];
    }
    $url = phidias_url() . '/' . ltrim($ruta, '/');
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . phidias_token(),
            'Accept: application/json',
        ],
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errCurl = curl_error($ch);
    curl_close($ch);

    if ($cuerpo === false) return [false, 'No se pudo conectar con Phidias: ' . $errCurl];
    if ($codigo === 401 || $codigo === 403) return [false, 'Phidias rechazó el token (HTTP ' . $codigo . '). Revísalo en Ajustes.'];
    if ($codigo >= 400) return [false, 'Phidias respondió HTTP ' . $codigo . '.'];

    $datos = json_decode((string) $cuerpo, true);
    if (!is_array($datos)) return [false, 'La respuesta de Phidias no es JSON válido.'];

    return [true, $datos];
}

// ------------------------------------------------------------ normalización --

/**
 * Pasa un nombre en mayúsculas sostenidas a capitalización normal,
 * respetando las partículas de los apellidos compuestos.
 */
function nombre_propio(string $s): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    if ($s === '') return '';
    $minusculas = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'das', 'do', 'dos', 'van', 'von', 'di', 'der', 'den'];
    $partes = explode(' ', mb_strtolower($s, 'UTF-8'));
    foreach ($partes as $i => $p) {
        if ($i > 0 && in_array($p, $minusculas, true)) continue;
        $partes[$i] = mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8');
    }
    return implode(' ', $partes);
}

/** Extrae el número de grado del nombre del grupo: K10C → 10, K6A → 6. */
function phidias_grado_de(string $grupo): ?int {
    if (preg_match('/^K\s*(\d{1,2})\s*[A-Z]?$/iu', trim($grupo), $m)) {
        $n = (int) $m[1];
        return ($n >= 1 && $n <= 12) ? $n : null;
    }
    return null;
}

/** Id del nivel del plan de aula que corresponde a un grado numérico. */
function nivel_por_grado(?int $grado): ?int {
    if ($grado === null) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (filas('SELECT id, grado FROM niveles') as $n) {
            if (preg_match('/(\d{1,2})/', $n['grado'], $m)) $cache[(int) $m[1]] = (int) $n['id'];
        }
    }
    return $cache[$grado] ?? null;
}

// ------------------------------------------------------------------ grupos --

/**
 * Devuelve los grupos de matrícula con sus estudiantes normalizados.
 * Usa la caché en disco salvo que se pida refrescar.
 *
 * Cada grupo: [seccion_id, curso (K10C), klasse (KLASSE 10), etapa (SECUNDARIA),
 *              grado (int|null), nivel_id (int|null), estudiantes[]]
 * Cada estudiante: [id_externo, codigo, nombre, apellidos, email, documento, estado]
 */
function phidias_cursos(bool $refrescar = false): array {
    $archivo = phidias_archivo_cache();

    if (!$refrescar && is_file($archivo) && (time() - filemtime($archivo)) < PHIDIAS_CACHE_MINUTOS * 60) {
        $crudo = json_decode((string) file_get_contents($archivo), true);
        if (is_array($crudo)) return [true, phidias_aplanar($crudo), (int) filemtime($archivo)];
    }

    [$ok, $datos] = phidias_get('/1/course/consolidate');
    if (!$ok) {
        // Si la llamada falla pero hay caché vieja, se usa igual y se avisa.
        if (is_file($archivo)) {
            $crudo = json_decode((string) file_get_contents($archivo), true);
            if (is_array($crudo)) return [true, phidias_aplanar($crudo), (int) filemtime($archivo), $datos];
        }
        return [false, $datos, 0];
    }

    $dir = dirname($archivo);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($archivo, json_encode($datos, JSON_UNESCAPED_UNICODE));

    return [true, phidias_aplanar($datos), time()];
}

/** Convierte la respuesta anidada en la lista plana de grupos. */
function phidias_aplanar(array $datos): array {
    $salida = [];
    foreach ($datos as $etapa) {
        $nombreEtapa = (string) ($etapa['name'] ?? '');
        foreach ($etapa['courses'] ?? [] as $klasse) {
            $nombreKlasse = (string) ($klasse['name'] ?? '');
            foreach ($klasse['sections'] ?? [] as $sec) {
                $curso = trim((string) ($sec['name'] ?? ''));
                if ($curso === '') continue;
                $grado = phidias_grado_de($curso);

                $estudiantes = [];
                foreach ($sec['students'] ?? [] as $e) {
                    $codigo = isset($e['code']) && $e['code'] !== '' ? (string) $e['code'] : '';
                    $email  = mb_strtolower(trim((string) ($e['email'] ?? '')));
                    // Si falta el correo pero hay código, se arma el institucional.
                    if ($email === '' && $codigo !== '') $email = $codigo . '@colegioaleman.edu.co';

                    $estudiantes[] = [
                        'id_externo' => (int) ($e['id'] ?? 0),
                        'codigo'     => $codigo,
                        'nombre'     => nombre_propio((string) ($e['firstname'] ?? '')),
                        'apellidos'  => nombre_propio((string) ($e['lastname'] ?? '')),
                        'email'      => $email,
                        'documento'  => isset($e['document']) && $e['document'] !== '' ? (string) $e['document'] : null,
                        'estado'     => (string) ($e['enrollment']['status'] ?? ''),
                    ];
                }
                usort($estudiantes, fn($a, $b) => strcmp($a['apellidos'] . $a['nombre'], $b['apellidos'] . $b['nombre']));

                $salida[] = [
                    'seccion_id'   => (int) ($sec['id'] ?? 0),
                    'curso'        => $curso,
                    'klasse'       => $nombreKlasse,
                    'etapa'        => $nombreEtapa,
                    'grado'        => $grado,
                    'nivel_id'     => nivel_por_grado($grado),
                    'estudiantes'  => $estudiantes,
                ];
            }
        }
    }
    return $salida;
}

/** Busca un grupo de Phidias por su nombre (K10C). */
function phidias_curso(array $cursos, string $nombre): ?array {
    foreach ($cursos as $c) if (strcasecmp($c['curso'], $nombre) === 0) return $c;
    return null;
}

// -------------------------------------------------------------- importación --

/**
 * Crea o reutiliza la cuenta de un estudiante de Phidias.
 * Devuelve [id, estado] donde estado es 'creado', 'existente' o 'error:<motivo>'.
 * Las contraseñas nuevas se acumulan en $credenciales para entregarlas al colegio.
 */
function phidias_asegurar_estudiante(array $e, ?int $colegioId, array &$credenciales): array {
    if ($e['email'] === '' || !filter_var($e['email'], FILTER_VALIDATE_EMAIL)) {
        return [0, 'error:sin correo válido'];
    }

    $u = fila('SELECT id, rol FROM usuarios WHERE email = ?', [$e['email']]);
    if (!$u && $e['codigo'] !== '') {
        $u = fila('SELECT id, rol FROM usuarios WHERE codigo_externo = ?', [$e['codigo']]);
    }

    if ($u) {
        if ($u['rol'] !== 'estudiante') return [(int) $u['id'], 'error:el correo pertenece a ' . $u['rol']];
        actualizar('usuarios', [
            'nombre'         => $e['nombre'] ?: 'Estudiante',
            'apellidos'      => $e['apellidos'],
            'documento'      => $e['documento'],
            'codigo_externo' => $e['codigo'] ?: null,
            'origen'         => 'phidias',
        ], 'id = :id', ['id' => $u['id']]);
        return [(int) $u['id'], 'existente'];
    }

    $clave = 'Vcp' . codigo_aleatorio(6) . random_int(10, 99);
    [$ok, $res] = crear_usuario([
        'nombre'     => $e['nombre'] ?: 'Estudiante',
        'apellidos'  => $e['apellidos'],
        'email'      => $e['email'],
        'clave'      => $clave,
        'rol'        => 'estudiante',
        'estado'     => 'activo',
        'colegio_id' => $colegioId,
        'documento'  => $e['documento'],
    ]);
    if (!$ok) return [0, 'error:' . $res];

    $id = (int) $res;
    actualizar('usuarios', [
        'codigo_externo' => $e['codigo'] ?: null,
        'origen'         => 'phidias',
    ], 'id = :id', ['id' => $id]);

    $credenciales[] = [$e['codigo'], trim($e['apellidos'] . ', ' . $e['nombre']), $e['email'], $clave];
    return [$id, 'creado'];
}
