<?php
/**
 * Datos de demostración del portal.
 *
 * Docentes, estudiantes, grupos, asignaciones con entregas en distintos
 * estados, datos comerciales y bitácoras: lo justo para recorrer el portal
 * recién instalado sin haber cargado todavía la matrícula real.
 *
 * Se incluye desde instalar.php y comparte su ámbito, así que usa las
 * variables que este ya preparó ($colegioId, $idNivel, $idActividad,
 * $NOMBRES_DEMO). **No debe ejecutarse en un servidor con matrícula real**:
 * para eso está el modo --sin-demo del instalador.
 */
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
