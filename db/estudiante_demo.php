<?php
/**
 * VCodePro · Estudiante de recorrido completo
 *
 * Crea (o actualiza) una cuenta de estudiante y la matricula en un grupo por
 * cada nivel del plan de aula, con todas las actividades de ese nivel asignadas.
 * Sirve para revisar el banco completo tal como lo ve un estudiante.
 *
 * Uso:  php db/estudiante_demo.php [correo] [contraseña]
 *
 * Si la cuenta ya existe y no se indica contraseña, se conserva la que tenía.
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar nada.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/academico.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la consola.');
}

$email = $argv[1] ?? 'tic@colegioaleman.edu.co';

// La contraseña solo se toca si se indica de forma explícita. Si la cuenta ya
// existe y no se pasa ninguna, conserva la que tenía.
$claveIndicada = $argv[2] ?? (getenv('VCP_DEMO_PASS') ?: '');
$clave = $claveIndicada !== '' ? $claveIndicada : 'Vcp' . bin2hex(random_bytes(4)) . random_int(10, 99);

$nombre = 'Coordinación';
$apellidos = 'TIC';

echo "Estudiante de recorrido: $email\n";

// ------------------------------------------------------------- colegio ----
$colegioId = (int) (valor('SELECT id FROM colegios ORDER BY id LIMIT 1') ?: 0);

// -------------------------------------------------------------- cuenta ----
$u = fila('SELECT * FROM usuarios WHERE email = ?', [$email]);
if ($u) {
    $datos = [
        'rol'        => 'estudiante',
        'estado'     => 'activo',
        'colegio_id' => $u['colegio_id'] ?: ($colegioId ?: null),
    ];
    if ($claveIndicada !== '') $datos['password_hash'] = password_hash($clave, PASSWORD_DEFAULT);
    actualizar('usuarios', $datos, 'id = :id', ['id' => $u['id']]);
    $estudianteId = (int) $u['id'];
    echo "  · cuenta existente actualizada (id $estudianteId)"
       . ($claveIndicada !== '' ? ', contraseña cambiada' : ', contraseña sin cambios') . "\n";
} else {
    [$ok, $res] = crear_usuario([
        'nombre'     => $nombre,
        'apellidos'  => $apellidos,
        'email'      => $email,
        'clave'      => $clave,
        'rol'        => 'estudiante',
        'estado'     => 'activo',
        'colegio_id' => $colegioId ?: null,
        'cargo'      => 'Cuenta de revisión del banco de actividades',
    ]);
    if (!$ok) { fwrite(STDERR, "Error: $res\n"); exit(1); }
    $estudianteId = (int) $res;
    echo "  · cuenta creada (id $estudianteId) · contraseña: $clave\n";
}

// -------------------------------------------------------------- docentes --
$docDiseno = (int) (valor('SELECT id FROM usuarios WHERE email = ?', ['docente.diseno@colegioaleman.edu.co']) ?: 0);
$docInfo   = (int) (valor('SELECT id FROM usuarios WHERE email = ?', ['docente.informatica@colegioaleman.edu.co']) ?: 0);
$docDiseno = $docDiseno ?: (int) valor('SELECT id FROM usuarios WHERE rol = "docente" ORDER BY id LIMIT 1', [], 0);
$docInfo   = $docInfo ?: $docDiseno;
if (!$docDiseno) { fwrite(STDERR, "Error: no hay ningún docente registrado.\n"); exit(1); }

// ------------------------------------------- un grupo por nivel del plan --
$anio = (int) date('Y');
$totalGrupos = 0; $totalAsig = 0; $totalEntregas = 0;

foreach (filas('SELECT * FROM niveles ORDER BY orden') as $n) {
    $docente = in_array($n['codigo'], ['N11', 'N12'], true) ? $docInfo : $docDiseno;
    $nombreGrupo = 'Recorrido ' . $n['grado'] . ' · plan completo';

    $grupoId = (int) (valor('SELECT id FROM grupos WHERE nombre = ? AND anio = ?', [$nombreGrupo, $anio]) ?: 0);
    if (!$grupoId) {
        do { $codigo = codigo_aleatorio(8); } while (valor('SELECT id FROM grupos WHERE codigo = ?', [$codigo]));
        $grupoId = insertar('grupos', [
            'colegio_id'   => $colegioId ?: null,
            'docente_id'   => $docente,
            'nivel_id'     => (int) $n['id'],
            'nombre'       => $nombreGrupo,
            'anio'         => $anio,
            'periodo'      => ajuste('periodo_actual', 'Periodo 1'),
            'codigo'       => $codigo,
            'jornada'      => 'Única',
            'ia_permitida' => 1,
            'estado'       => 'activo',
        ]);
        $totalGrupos++;
    }

    // Matrícula del estudiante.
    if (!valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$grupoId, $estudianteId])) {
        insertar('grupo_estudiantes', [
            'grupo_id' => $grupoId, 'estudiante_id' => $estudianteId, 'estado' => 'activo',
        ]);
    }

    // Todas las actividades publicadas del nivel, con fechas escalonadas.
    $orden = 0;
    foreach (filas('SELECT id, codigo, sesiones FROM actividades WHERE nivel_id = ? AND publicada = 1 ORDER BY orden', [$n['id']]) as $a) {
        $orden++;
        $inicio  = date('Y-m-d', strtotime('-' . max(0, 7 - $orden) . ' days'));
        $entrega = date('Y-m-d', strtotime('+' . ($orden * 7) . ' days'));

        $asigId = (int) (valor('SELECT id FROM asignaciones WHERE grupo_id = ? AND actividad_id = ?', [$grupoId, $a['id']]) ?: 0);
        if (!$asigId) {
            $asigId = insertar('asignaciones', [
                'grupo_id'      => $grupoId,
                'actividad_id'  => (int) $a['id'],
                'docente_id'    => $docente,
                'fecha_inicio'  => $inicio,
                'fecha_entrega' => $entrega,
                'instrucciones' => 'Recorrido completo del plan de aula. Trabaja las cuatro fases del ciclo de diseño y adjunta la bitácora con la entrega.',
                'ia_permitida'  => 1,
                'estado'        => 'abierta',
            ]);
            $totalAsig++;
        }

        if (!valor('SELECT id FROM entregas WHERE asignacion_id = ? AND estudiante_id = ?', [$asigId, $estudianteId])) {
            insertar('entregas', [
                'asignacion_id' => $asigId,
                'estudiante_id' => $estudianteId,
                'estado'        => 'pendiente',
            ]);
            $totalEntregas++;
        }
    }

    printf("  · %-6s %-42s %2d actividades\n", $n['grado'], $nombreGrupo, $orden);
}

$visibles = (int) valor('SELECT COUNT(*) FROM entregas WHERE estudiante_id = ?', [$estudianteId], 0);

echo "\nGrupos nuevos: $totalGrupos · asignaciones nuevas: $totalAsig · entregas nuevas: $totalEntregas\n";
echo "Actividades visibles para $email: $visibles\n";
echo "Entra en " . BASE_URL . "/portal/login.php\n";
