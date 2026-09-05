<?php
/**
 * VCodePro · Instalador del portal académico
 *
 * Crea la base de datos, aplica el esquema y carga el banco de actividades,
 * el administrador inicial y un colegio de demostración.
 *
 * Uso:  http://localhost:8080/vcodeproplus/instalar.php
 *   o:  php instalar.php
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar datos.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/db/seed/comun.php';

$cli  = PHP_SAPI === 'cli';
$log  = [];
$fallo = null;

function paso(string $texto): void { global $log; $log[] = ['ok', $texto]; }
function nota(string $texto): void { global $log; $log[] = ['info', $texto]; }

// -------------------------------------------------------- datos iniciales --
/**
 * Contraseña del administrador inicial. No se escribe en el código: se toma de
 * la variable de entorno VCP_ADMIN_PASS, del primer argumento en consola, o se
 * genera una al azar que se muestra al terminar la instalación.
 *
 *   php instalar.php "MiClaveSegura2026"
 *   set VCP_ADMIN_PASS=MiClaveSegura2026  (Windows)
 *
 * Si la cuenta ya existe, el instalador no toca su contraseña.
 */
define('ADMIN_EMAIL', getenv('VCP_ADMIN_EMAIL') ?: 'eortiz@colegioaleman.edu.co');

$claveIndicada = getenv('VCP_ADMIN_PASS') ?: (($cli && isset($argv[1])) ? $argv[1] : '');
$claveGenerada = $claveIndicada === '';
define('ADMIN_CLAVE', $claveGenerada
    ? 'Vcp' . bin2hex(random_bytes(5)) . random_int(10, 99)
    : $claveIndicada);

$NOMBRES_DEMO = [
    ['Mariana', 'Acosta Rivera'], ['Samuel', 'Bermúdez Lozano'], ['Valeria', 'Cárdenas Pineda'],
    ['Tomás', 'Duarte Salgado'], ['Isabella', 'Escobar Nieto'], ['Martín', 'Franco Villalba'],
    ['Sofía', 'Gaitán Restrepo'], ['Emilio', 'Hurtado Bejarano'], ['Antonia', 'Ibáñez Cortés'],
    ['Jerónimo', 'Jaramillo Ochoa'], ['Luciana', 'Küpper Moreno'], ['Simón', 'Lemus Ardila'],
    ['Julieta', 'Mahecha Quintero'], ['Nicolás', 'Navarro Céspedes'], ['Renata', 'Ospina Camargo'],
    ['Emiliano', 'Peláez Sarmiento'], ['Camila', 'Quiroga Bustos'], ['Alejandro', 'Ramírez Solano'],
    ['Manuela', 'Suárez Delgado'], ['Daniel', 'Trujillo Ferreira'], ['Salomé', 'Uribe Machado'],
    ['Matías', 'Vanegas Olarte'], ['Gabriela', 'Wilches Peñaranda'], ['Andrés', 'Zapata Linares'],
];

try {
    // ------------------------------------------------ 1. base de datos ----
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PUERTO);
    $raiz = new PDO($dsn, DB_USUARIO, DB_CLAVE, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $raiz->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NOMBRE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    paso('Base de datos <code>' . DB_NOMBRE . '</code> disponible.');

    require_once __DIR__ . '/includes/db.php';
    $pdo = db();

    // ------------------------------------------------------ 2. esquema ----
    $sql = file_get_contents(__DIR__ . '/db/schema.sql');
    if ($sql === false) throw new RuntimeException('No se pudo leer db/schema.sql');
    // El esquema no contiene guiones dobles dentro de literales, así que se
    // pueden retirar los comentarios de línea antes de separar las sentencias.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sentencias = array_filter(array_map('trim', explode(';', $sql)));
    $creadas = 0;
    foreach ($sentencias as $s) {
        if ($s === '') continue;
        $pdo->exec($s);
        if (stripos($s, 'CREATE TABLE') !== false) $creadas++;
    }
    paso("Esquema aplicado: $creadas tablas verificadas.");

    // ---------------------------------------------- 2b. migraciones ligeras --
    // CREATE TABLE IF NOT EXISTS no agrega columnas a tablas ya existentes,
    // así que las incorporaciones posteriores se aplican aquí.
    $columnas = [
        ['usuarios', 'codigo_externo', "VARCHAR(40) DEFAULT NULL AFTER tema"],
        ['usuarios', 'origen',         "VARCHAR(20) NOT NULL DEFAULT 'local' AFTER codigo_externo"],
        ['grupos',   'curso_externo',  "VARCHAR(60) DEFAULT NULL AFTER codigo"],
        ['facturas', 'referencia_pago', "VARCHAR(60) DEFAULT NULL AFTER estado"],
        ['facturas', 'pasarela',        "VARCHAR(30) DEFAULT NULL AFTER referencia_pago"],
        ['facturas', 'pagada_en',       "DATETIME DEFAULT NULL AFTER pasarela"],
    ];
    $agregadas = 0;
    foreach ($columnas as [$tabla, $columna, $definicion]) {
        $existe = valor('SELECT COUNT(*) FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                        [DB_NOMBRE, $tabla, $columna], 0);
        if (!$existe) {
            $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
            $agregadas++;
        }
    }
    $idxExterno = valor('SELECT COUNT(*) FROM information_schema.STATISTICS
                          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "usuarios" AND INDEX_NAME = "idx_usuarios_externo"',
                        [DB_NOMBRE], 0);
    if (!$idxExterno) {
        $pdo->exec('ALTER TABLE usuarios ADD INDEX idx_usuarios_externo (codigo_externo)');
        $agregadas++;
    }
    if ($agregadas) paso("Migraciones aplicadas: $agregadas cambio(s) de estructura.");

    // ------------------------------------------------------ 3. niveles ----
    $niveles = require __DIR__ . '/db/seed/niveles.php';
    $idNivel = [];
    foreach ($niveles as $n) {
        $existente = valor('SELECT id FROM niveles WHERE codigo = ?', [$n['codigo']]);
        if ($existente) {
            $idNivel[$n['codigo']] = (int) $existente;
            actualizar('niveles', $n, 'id = :__id', ['__id' => $existente]);
        } else {
            $idNivel[$n['codigo']] = insertar('niveles', $n);
        }
    }
    paso('Niveles cargados: ' . count($idNivel) . ' (6.º a 12.º).');

    // -------------------------------------------------- 4. actividades ----
    $totalAct = 0; $totalFases = 0; $totalRub = 0;
    // Banco complementario de actividades con uso de asistentes de IA,
    // indexado por código de nivel.
    $archivoIa = __DIR__ . '/db/seed/actividades_ia.php';
    $bancoIa = is_file($archivoIa) ? require $archivoIa : [];

    foreach (array_keys($idNivel) as $codNivel) {
        $archivo = __DIR__ . '/db/seed/actividades_' . strtolower($codNivel) . '.php';
        $lista = is_file($archivo) ? require $archivo : [];
        $lista = array_merge($lista, $bancoIa[$codNivel] ?? []);
        if (!$lista) { nota("Sin banco de actividades para $codNivel."); continue; }
        $orden = 0;
        foreach ($lista as $a) {
            $orden++;
            $datos = [
                'nivel_id'            => $idNivel[$codNivel],
                'codigo'              => $a['codigo'],
                'titulo'              => $a['titulo'],
                'resumen'             => $a['resumen'],
                'descripcion'         => $a['descripcion'],
                'pregunta_indagacion' => $a['pregunta'],
                'criterios_ib'        => in_array($codNivel, ['N11', 'N12'], true) ? 'A,B,C,D,E' : 'A,B,C,D',
                'contexto_global'     => $a['contexto'],
                'concepto_clave'      => $a['concepto'],
                'perfil_ib'           => $a['perfil'],
                'atl'                 => $a['atl'],
                'objetivos'           => $a['objetivos'],
                'entregables'         => $a['entregables'],
                'lenguaje'            => $a['lenguaje'],
                'dificultad'          => $a['dificultad'],
                'sesiones'            => $a['sesiones'],
                'horas'               => $a['horas'],
                'ia_sugerida'         => $a['ia'],
                'codigo_inicial'      => $a['codigo_inicial'] ?? null,
                'orden'               => $orden,
                'publicada'           => 1,
            ];
            $actId = valor('SELECT id FROM actividades WHERE codigo = ?', [$a['codigo']]);
            if ($actId) {
                $actId = (int) $actId;
                actualizar('actividades', $datos, 'id = :__id', ['__id' => $actId]);
                borrar('actividad_fases', 'actividad_id = ?', [$actId]);
                borrar('actividad_recursos', 'actividad_id = ?', [$actId]);
                borrar('rubrica_criterios', 'actividad_id = ?', [$actId]);
            } else {
                $actId = insertar('actividades', $datos);
            }
            $totalAct++;

            $o = 0;
            foreach ($a['fases'] as [$fase, $titulo, $instr, $entregable, $minutos]) {
                insertar('actividad_fases', [
                    'actividad_id'  => $actId,
                    'fase'          => $fase,
                    'titulo'        => $titulo,
                    'instrucciones' => $instr,
                    'entregable'    => $entregable,
                    'minutos'       => $minutos,
                    'orden'         => ++$o,
                ]);
                $totalFases++;
            }
            foreach (($a['recursos'] ?? []) as [$tipo, $titulo, $detalle]) {
                insertar('actividad_recursos', [
                    'actividad_id' => $actId,
                    'tipo'         => $tipo,
                    'titulo'       => $titulo,
                    'detalle'      => $detalle,
                ]);
            }
            foreach (rubrica_para($codNivel, $a['objeto']) as [$cr, $nombre, $d12, $d34, $d56, $d78, $max]) {
                insertar('rubrica_criterios', [
                    'actividad_id'  => $actId,
                    'criterio'      => $cr,
                    'nombre'        => $nombre,
                    'descriptor_12' => $d12,
                    'descriptor_34' => $d34,
                    'descriptor_56' => $d56,
                    'descriptor_78' => $d78,
                    'maximo'        => $max,
                ]);
                $totalRub++;
            }
        }
    }
    paso("Banco de actividades: $totalAct actividades, $totalFases fases del ciclo de diseño y $totalRub criterios de rúbrica.");

    // ---------------------------------------------------- 5. insignias ----
    $insignias = [
        ['primer_paso',   'Primer paso',           'Completaste tu primera actividad del ciclo de diseño.', 'A', 10],
        ['ciclo_completo','Ciclo completo',        'Terminaste las cuatro fases de una misma actividad.',   'B', 20],
        ['puntual',       'Siempre a tiempo',      'Cinco entregas consecutivas antes de la fecha límite.', 'C', 25],
        ['bitacora_viva', 'Bitácora viva',         'Veinte entradas de bitácora registradas.',              'D', 30],
        ['criterio_alto', 'Descriptor alto',       'Alcanzaste 7 u 8 en un criterio de la rúbrica.',        'E', 40],
        ['indagador',     'Indagador',             'Diez actividades con la fase de indagación completa.',  'F', 30],
        ['depurador',     'Cazador de errores',    'Completaste una actividad de depuración sin ayuda.',    'G', 25],
        ['constructor',   'Constructor',           'Entregaste un proyecto insignia de tu nivel.',          'H', 50],
        ['etico',         'Uso íntegro de la IA',  'Declaraste el uso de IA en cinco entregas seguidas.',   'I', 35],
        ['mentor',        'Mentor del grupo',      'Ayudaste en la evaluación cruzada de tres compañeros.', 'J', 30],
    ];
    foreach ($insignias as [$cod, $nom, $desc, $ico, $pts]) {
        if (!valor('SELECT id FROM insignias WHERE codigo = ?', [$cod])) {
            insertar('insignias', ['codigo' => $cod, 'nombre' => $nom, 'descripcion' => $desc, 'icono' => $ico, 'puntos' => $pts]);
        }
    }
    paso('Insignias del portal: ' . count($insignias) . '.');

    // ------------------------------------------------------- 6. ajustes ---
    $ajustesBase = [
        'colegio_principal' => 'Colegio Alemán de Barranquilla',
        'anio_escolar'      => (string) (int) date('Y'),
        'periodo_actual'    => 'Periodo 3',
        'nota_aprobacion'   => '4',
        'ia_global'         => '1',
        'registro_abierto'  => '1',
        'contacto_soporte'  => 'soporte@vcodepro.de',
    ];
    foreach ($ajustesBase as $k => $v) {
        if (valor('SELECT COUNT(*) FROM ajustes WHERE clave = ?', [$k]) == 0) guardar_ajuste($k, $v);
    }
    paso('Ajustes del sistema inicializados.');

    // ------------------------------------------------------- 7. colegio ---
    // El NIT, la dirección y el teléfono se completan desde el panel de
    // administración: no se inventan datos de la institución.
    $colegioId = (int) (valor('SELECT id FROM colegios WHERE slug = ?', ['colegio-aleman-barranquilla']) ?: 0);
    if (!$colegioId) {
        $colegioId = insertar('colegios', [
            'nombre'      => 'Colegio Alemán de Barranquilla',
            'slug'        => 'colegio-aleman-barranquilla',
            'pais'        => 'Colombia',
            'ciudad'      => 'Barranquilla',
            'email'       => 'tecnologia@colegioaleman.edu.co',
            'programa_ib' => 'PAI y Programa del Diploma',
            'estado'      => 'activo',
        ]);
    }
    paso('Colegio principal registrado.');

    // ---------------------------------------------------- 8. usuarios -----
    require_once __DIR__ . '/includes/auth.php';

    $adminId = (int) (valor('SELECT id FROM usuarios WHERE email = ?', [ADMIN_EMAIL]) ?: 0);
    $adminNuevo = !$adminId;
    if (!$adminId) {
        $adminId = insertar('usuarios', [
            'colegio_id'    => $colegioId,
            'nombre'        => 'Edwin',
            'apellidos'     => 'Ortiz',
            'email'         => ADMIN_EMAIL,
            'password_hash' => password_hash(ADMIN_CLAVE, PASSWORD_DEFAULT),
            'rol'           => 'admin',
            'estado'        => 'activo',
            'cargo'         => 'Coordinación de Tecnología',
        ]);
        paso('Administrador creado: <code>' . ADMIN_EMAIL . '</code>'
            . ($claveGenerada
                ? ' · contraseña generada: <code>' . ADMIN_CLAVE . '</code> — anótala y cámbiala al entrar'
                : ' · con la contraseña que indicaste'));
    } else {
        nota('El administrador <code>' . ADMIN_EMAIL . '</code> ya existía; no se modificó su contraseña.');
    }

    // Docentes de demostración
    $docentes = [
        ['docente.diseno@colegioaleman.edu.co', 'Laura', 'Betancur Ríos', 'Docente de Diseño · PAI'],
        ['docente.informatica@colegioaleman.edu.co', 'Óscar', 'Mendieta Rojas', 'Docente de Informática · DP'],
    ];
    $idDocente = [];
    foreach ($docentes as [$email, $nom, $ape, $cargo]) {
        $id = (int) (valor('SELECT id FROM usuarios WHERE email = ?', [$email]) ?: 0);
        if (!$id) {
            $id = insertar('usuarios', [
                'colegio_id'    => $colegioId,
                'nombre'        => $nom, 'apellidos' => $ape, 'email' => $email,
                'password_hash' => password_hash('Docente2026*', PASSWORD_DEFAULT),
                'rol' => 'docente', 'estado' => 'activo', 'cargo' => $cargo,
            ]);
        }
        $idDocente[$email] = $id;
    }

    // Cliente de demostración
    $clienteEmail = 'rectoria@colegioaleman.edu.co';
    $clienteId = (int) (valor('SELECT id FROM usuarios WHERE email = ?', [$clienteEmail]) ?: 0);
    if (!$clienteId) {
        $clienteId = insertar('usuarios', [
            'colegio_id'    => $colegioId,
            'nombre'        => 'Patricia', 'apellidos' => 'Alzate Guzmán', 'email' => $clienteEmail,
            'password_hash' => password_hash('Cliente2026*', PASSWORD_DEFAULT),
            'rol' => 'cliente', 'estado' => 'activo', 'cargo' => 'Rectoría',
        ]);
    }
    paso('Usuarios de demostración: 2 docentes y 1 cliente.');

    // Estudiantes
    $idEstudiante = [];
    foreach ($NOMBRES_DEMO as $i => [$nom, $ape]) {
        $email = strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom)))
               . '.' . strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', explode(' ', $ape)[0])))
               . '@estudiantes.colegioaleman.edu.co';
        $id = (int) (valor('SELECT id FROM usuarios WHERE email = ?', [$email]) ?: 0);
        if (!$id) {
            $id = insertar('usuarios', [
                'colegio_id'    => $colegioId,
                'nombre'        => $nom, 'apellidos' => $ape, 'email' => $email,
                'password_hash' => password_hash('Estudiante2026*', PASSWORD_DEFAULT),
                'rol' => 'estudiante', 'estado' => 'activo',
                'documento' => (string) (1030500000 + $i * 137),
            ]);
        }
        $idEstudiante[] = $id;
    }
    paso('Estudiantes de demostración: ' . count($idEstudiante) . '.');

    // ------------------------------------------------------- 9. grupos ----
    $anio = (int) date('Y');
    $gruposDemo = [
        ['8.º A · Diseño', 'N8', 'docente.diseno@colegioaleman.edu.co', 0, 8],
        ['10.º B · Diseño e Informática', 'N10', 'docente.diseno@colegioaleman.edu.co', 8, 8],
        ['11.º · Informática NM y NS', 'N11', 'docente.informatica@colegioaleman.edu.co', 16, 8],
    ];
    $idGrupo = [];
    foreach ($gruposDemo as [$nombre, $nivelCod, $docEmail, $desde, $cuantos]) {
        $gid = (int) (valor('SELECT id FROM grupos WHERE nombre = ? AND anio = ?', [$nombre, $anio]) ?: 0);
        if (!$gid) {
            $gid = insertar('grupos', [
                'colegio_id' => $colegioId,
                'docente_id' => $idDocente[$docEmail],
                'nivel_id'   => $idNivel[$nivelCod],
                'nombre'     => $nombre,
                'anio'       => $anio,
                'periodo'    => 'Periodo 3',
                'codigo'     => codigo_aleatorio(8),
                'jornada'    => 'Única',
                'estado'     => 'activo',
            ]);
        }
        $idGrupo[$nombre] = $gid;
        foreach (array_slice($idEstudiante, $desde, $cuantos) as $eid) {
            if (!valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$gid, $eid])) {
                insertar('grupo_estudiantes', ['grupo_id' => $gid, 'estudiante_id' => $eid, 'estado' => 'activo']);
            }
        }
    }
    paso('Grupos de demostración: ' . count($idGrupo) . ' con 8 estudiantes cada uno.');

    // -------------------------------------------- 10. asignaciones -------
    $totalAsig = 0; $totalEntregas = 0;
    foreach ($gruposDemo as $gi => [$nombre, $nivelCod, $docEmail, , ]) {
        $gid = $idGrupo[$nombre];
        $acts = filas('SELECT id FROM actividades WHERE nivel_id = ? ORDER BY orden LIMIT 4', [$idNivel[$nivelCod]]);
        $estudiantes = filas('SELECT estudiante_id FROM grupo_estudiantes WHERE grupo_id = ?', [$gid]);
        foreach ($acts as $k => $act) {
            $inicio  = date('Y-m-d', strtotime("-" . (28 - $k * 7) . " days"));
            $entrega = date('Y-m-d', strtotime("+" . ($k * 7 - 7) . " days"));
            $aid = (int) (valor('SELECT id FROM asignaciones WHERE grupo_id = ? AND actividad_id = ?', [$gid, $act['id']]) ?: 0);
            if (!$aid) {
                $aid = insertar('asignaciones', [
                    'grupo_id'      => $gid,
                    'actividad_id'  => $act['id'],
                    'docente_id'    => $idDocente[$docEmail],
                    'fecha_inicio'  => $inicio,
                    'fecha_entrega' => $entrega,
                    'instrucciones' => 'Trabajo individual. Adjunta la bitácora del ciclo de diseño con la entrega.',
                    'estado'        => $k === 3 ? 'abierta' : 'abierta',
                ]);
                $totalAsig++;
            }
            foreach ($estudiantes as $j => $e) {
                if (valor('SELECT id FROM entregas WHERE asignacion_id = ? AND estudiante_id = ?', [$aid, $e['estudiante_id']])) continue;
                // Progreso variado para que los paneles muestren datos realistas.
                $estado = match (true) {
                    $k === 0 => ($j % 5 === 0 ? 'rehacer' : 'revisada'),
                    $k === 1 => ($j % 4 === 0 ? 'entregada' : 'revisada'),
                    $k === 2 => ($j % 3 === 0 ? 'en_progreso' : 'entregada'),
                    default  => ($j % 2 === 0 ? 'en_progreso' : 'pendiente'),
                };
                $progreso = match ($estado) {
                    'revisada', 'entregada' => 100,
                    'rehacer'     => 75,
                    'en_progreso' => [25, 50, 75][$j % 3],
                    default       => 0,
                };
                insertar('entregas', [
                    'asignacion_id' => $aid,
                    'estudiante_id' => $e['estudiante_id'],
                    'estado'        => $estado,
                    'progreso'      => $progreso,
                    'entregado_en'  => in_array($estado, ['entregada', 'revisada', 'rehacer'], true)
                                       ? date('Y-m-d H:i:s', strtotime($entrega . ' -1 day')) : null,
                ]);
                $totalEntregas++;
            }
        }
    }
    paso("Asignaciones de demostración: $totalAsig con $totalEntregas entregas en distintos estados.");

    // ------------------------------------------------- 11. comercial -----
    if (!valor('SELECT COUNT(*) FROM licencias')) {
        $licId = insertar('licencias', [
            'colegio_id' => $colegioId,
            'cliente_id' => $clienteId,
            'clave'      => generar_clave_licencia('escuela'),
            'plan'       => 'escuela',
            'cupo'       => 100,
            'emitida_en' => date('Y-m-d', strtotime('-2 months')),
            'vence_en'   => date('Y-m-d', strtotime('+10 months')),
            'estado'     => 'activa',
            'notas'      => 'Licencia Escuela para el departamento de Tecnología.',
        ]);
        foreach (array_slice($idEstudiante, 0, 12) as $eid) {
            $u = fila('SELECT nombre, apellidos, email FROM usuarios WHERE id = ?', [$eid]);
            insertar('licencia_puestos', [
                'licencia_id' => $licId,
                'nombre'      => trim($u['nombre'] . ' ' . $u['apellidos']),
                'email'       => $u['email'],
                'dispositivo' => 'Sala de cómputo ' . (1 + ($eid % 3)),
            ]);
        }
        insertar('facturas', [
            'cliente_id' => $clienteId, 'licencia_id' => $licId,
            'numero'     => 'VCP-' . date('Y') . '-0001',
            'concepto'   => 'Licencia Escuela · 100 puestos · 12 meses',
            'monto'      => 5000000, 'moneda' => 'COP', 'estado' => 'pagada',
            'emitida_en' => date('Y-m-d', strtotime('-2 months')),
            'vence_en'   => date('Y-m-d', strtotime('-1 month')),
        ]);
        insertar('facturas', [
            'cliente_id' => $clienteId, 'licencia_id' => $licId,
            'numero'     => 'VCP-' . date('Y') . '-0042',
            'concepto'   => 'Formación docente · Talleres 1 a 4',
            'monto'      => 1800000, 'moneda' => 'COP', 'estado' => 'pendiente',
            'emitida_en' => date('Y-m-d', strtotime('-10 days')),
            'vence_en'   => date('Y-m-d', strtotime('+20 days')),
        ]);
        $tid = insertar('tickets', [
            'cliente_id' => $clienteId,
            'asunto'     => 'Despliegue de VCodePro en la sala 2',
            'categoria'  => 'tecnico', 'prioridad' => 'media', 'estado' => 'abierto',
        ]);
        insertar('ticket_mensajes', [
            'ticket_id' => $tid, 'autor_id' => $clienteId,
            'mensaje'   => 'Necesitamos instalar el editor en los 30 equipos de la sala 2 antes del inicio del periodo. ¿Existe una guía de despliegue desatendido?',
        ]);
        paso('Datos comerciales: 1 licencia con 12 puestos, 2 facturas y 1 ticket de soporte.');
    } else {
        nota('Ya existían licencias; no se generaron datos comerciales nuevos.');
    }

    // -------------------------------------------------- 12. bitácora -----
    if (!valor('SELECT COUNT(*) FROM bitacora')) {
        $ent = filas('SELECT id, estudiante_id FROM entregas WHERE estado IN ("revisada","entregada") LIMIT 12');
        foreach ($ent as $i => $e) {
            insertar('bitacora', [
                'estudiante_id' => $e['estudiante_id'],
                'entrega_id'    => $e['id'],
                'fase'          => ['indagar', 'desarrollar', 'crear', 'evaluar'][$i % 4],
                'titulo'        => ['Fuentes consultadas', 'Especificaciones acordadas', 'Primera versión funcionando', 'Prueba con usuarios'][$i % 4],
                'contenido'     => 'Registro de trabajo de la sesión. Se documentó la decisión tomada, lo que no funcionó y el siguiente paso previsto.',
                'minutos'       => 45 + ($i % 4) * 15,
            ]);
        }
        paso('Bitácoras de ejemplo cargadas.');
    }

} catch (Throwable $e) {
    $fallo = $e->getMessage();
}

// ------------------------------------------------------------- salida -----
if ($cli) {
    foreach ($log as [$t, $m]) echo ($t === 'ok' ? '[OK]   ' : '[..]   ') . strip_tags($m) . PHP_EOL;
    if ($fallo) { echo '[ERROR] ' . $fallo . PHP_EOL; exit(1); }
    echo PHP_EOL . 'Instalación completa. Portal: ' . BASE_URL . '/portal/login.php' . PHP_EOL;
    exit(0);
}
?><!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación del portal · VCodePro</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal.css">
</head>
<body class="portal">
<main class="pt-main" style="max-width:820px;margin:0 auto;">
  <div class="pt-head">
    <div>
      <p class="eyebrow">VCodePro</p>
      <h1><?= $fallo ? 'La instalación se detuvo' : 'Portal instalado' ?></h1>
      <p class="pt-sub">Capa académica IB sobre PHP y MySQL.</p>
    </div>
  </div>

  <?php if ($fallo): ?>
    <div class="aviso aviso-err"><strong>Error:</strong>&nbsp;<?= htmlspecialchars($fallo) ?></div>
    <p class="txt-muted">Revisa las credenciales en <code>includes/config.php</code> y que el servidor MySQL esté activo.</p>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-h"><h2>Registro de la instalación</h2></div>
    <ul class="linea">
      <?php foreach ($log as [$t, $m]): ?>
        <li><h4><?= $t === 'ok' ? '✓' : '·' ?> <?= $m ?></h4></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if (!$fallo): ?>
  <div class="panel">
    <div class="panel-h"><h2>Cuentas para entrar</h2><p>Cambia las contraseñas de demostración antes de publicar.</p></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Rol</th><th>Correo</th><th>Contraseña</th></tr></thead>
        <tbody>
          <tr><td>Administrador</td><td class="mono"><?= ADMIN_EMAIL ?></td><td class="mono"><?= $claveGenerada && $adminNuevo ? h(ADMIN_CLAVE) : "la que definiste al instalar" ?></td></tr>
          <tr><td>Docente (Diseño)</td><td class="mono">docente.diseno@colegioaleman.edu.co</td><td class="mono">Docente2026*</td></tr>
          <tr><td>Docente (Informática)</td><td class="mono">docente.informatica@colegioaleman.edu.co</td><td class="mono">Docente2026*</td></tr>
          <tr><td>Estudiante</td><td class="mono">mariana.acosta@estudiantes.colegioaleman.edu.co</td><td class="mono">Estudiante2026*</td></tr>
          <tr><td>Cliente</td><td class="mono">rectoria@colegioaleman.edu.co</td><td class="mono">Cliente2026*</td></tr>
        </tbody>
      </table>
    </div>
    <div class="form-acc">
      <a class="btn" href="<?= BASE_URL ?>/portal/login.php">Entrar al portal</a>
      <a class="btn btn-ghost" href="<?= BASE_URL ?>/index.html">Ir al sitio público</a>
    </div>
  </div>

  <div class="aviso aviso-warn">
    Por seguridad, elimina o renombra <code>instalar.php</code> cuando el portal esté en producción.
  </div>
  <?php endif; ?>
</main>
</body>
</html>
