<?php
/**
 * Lógica académica compartida: entregas, progreso por fases, calificación
 * con rúbrica IB e insignias.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Devuelve la entrega de un estudiante en una asignación, creándola si falta. */
function entrega_de(int $asignacionId, int $estudianteId): array {
    $e = fila('SELECT * FROM entregas WHERE asignacion_id = ? AND estudiante_id = ?', [$asignacionId, $estudianteId]);
    if ($e) return $e;
    $id = insertar('entregas', [
        'asignacion_id' => $asignacionId,
        'estudiante_id' => $estudianteId,
        'estado'        => 'pendiente',
    ]);
    return fila('SELECT * FROM entregas WHERE id = ?', [$id]) ?? [];
}

/**
 * Recalcula el porcentaje de avance de una entrega a partir de las fases
 * completadas del ciclo de diseño. Devuelve el porcentaje.
 */
function recalcular_progreso(int $entregaId): int {
    $e = fila('SELECT e.*, a.actividad_id FROM entregas e
                 JOIN asignaciones a ON a.id = e.asignacion_id
                WHERE e.id = ?', [$entregaId]);
    if (!$e) return 0;

    $total = (int) valor('SELECT COUNT(*) FROM actividad_fases WHERE actividad_id = ?', [$e['actividad_id']], 0);
    $hechas = (int) valor('SELECT COUNT(*) FROM entrega_fases WHERE entrega_id = ? AND completada = 1', [$entregaId], 0);
    $pct = porcentaje((float) $hechas, (float) $total);

    $estado = $e['estado'];
    if (in_array($estado, ['pendiente', 'en_progreso'], true)) {
        $estado = $pct > 0 ? 'en_progreso' : 'pendiente';
    }
    actualizar('entregas', ['progreso' => $pct, 'estado' => $estado], 'id = :id', ['id' => $entregaId]);

    if ($pct === 100 && $total > 0) otorgar_insignia((int) $e['estudiante_id'], 'ciclo_completo');
    if ($hechas > 0) otorgar_insignia((int) $e['estudiante_id'], 'primer_paso');

    return $pct;
}

/** Guarda el contenido de una fase para una entrega. */
function guardar_fase(int $entregaId, int $faseId, ?string $contenido, ?bool $completada = null): void {
    $ex = fila('SELECT * FROM entrega_fases WHERE entrega_id = ? AND fase_id = ?', [$entregaId, $faseId]);
    if ($ex) {
        $datos = [];
        if ($contenido !== null)  $datos['contenido'] = $contenido;
        if ($completada !== null) $datos['completada'] = $completada ? 1 : 0;
        if ($datos) actualizar('entrega_fases', $datos, 'id = :id', ['id' => $ex['id']]);
    } else {
        insertar('entrega_fases', [
            'entrega_id' => $entregaId,
            'fase_id'    => $faseId,
            'contenido'  => $contenido ?? '',
            'completada' => $completada ? 1 : 0,
        ]);
    }
}

/**
 * Suma los puntajes de la rúbrica y actualiza la nota final de la entrega.
 * Devuelve [obtenido, maximo, nota, descripcion].
 */
function calcular_nota(int $entregaId): array {
    $r = fila('SELECT COALESCE(SUM(c.puntaje), 0) AS obtenido,
                      COALESCE(SUM(rc.maximo), 0) AS maximo
                 FROM calificaciones c
                 JOIN rubrica_criterios rc ON rc.id = c.criterio_id
                WHERE c.entrega_id = ?', [$entregaId]);

    $obtenido = (float) ($r['obtenido'] ?? 0);
    $maximo   = (float) ($r['maximo'] ?? 0);
    if ($maximo <= 0) return [0.0, 0.0, null, ''];

    [$nota, $desc] = nota_ib($obtenido, $maximo);
    actualizar('entregas', [
        'nota_final' => $obtenido,
        'nota_letra' => $nota,
    ], 'id = :id', ['id' => $entregaId]);

    return [$obtenido, $maximo, $nota, $desc];
}

/** Concede una insignia si el usuario no la tiene todavía. */
function otorgar_insignia(int $usuarioId, string $codigo): bool {
    $i = fila('SELECT id, nombre FROM insignias WHERE codigo = ?', [$codigo]);
    if (!$i) return false;
    if (valor('SELECT id FROM usuario_insignias WHERE usuario_id = ? AND insignia_id = ?', [$usuarioId, $i['id']])) {
        return false;
    }
    insertar('usuario_insignias', ['usuario_id' => $usuarioId, 'insignia_id' => $i['id']]);
    notificar($usuarioId, 'Nueva insignia: ' . $i['nombre'], 'Se sumó a tu portafolio.', 'portal/estudiante/insignias.php', 'logro');
    return true;
}

/** Comprueba las insignias que dependen de acumulados y las concede. */
function revisar_insignias(int $estudianteId): void {
    $entradas = (int) valor('SELECT COUNT(*) FROM bitacora WHERE estudiante_id = ?', [$estudianteId], 0);
    if ($entradas >= 20) otorgar_insignia($estudianteId, 'bitacora_viva');

    $altas = (int) valor('SELECT COUNT(*) FROM calificaciones c
                            JOIN entregas e ON e.id = c.entrega_id
                           WHERE e.estudiante_id = ? AND c.puntaje >= 7', [$estudianteId], 0);
    if ($altas > 0) otorgar_insignia($estudianteId, 'criterio_alto');

    $puntuales = (int) valor('SELECT COUNT(*) FROM entregas e
                                JOIN asignaciones a ON a.id = e.asignacion_id
                               WHERE e.estudiante_id = ? AND e.entregado_en IS NOT NULL
                                 AND DATE(e.entregado_en) <= a.fecha_entrega', [$estudianteId], 0);
    if ($puntuales >= 5) otorgar_insignia($estudianteId, 'puntual');

    $indagacion = (int) valor('SELECT COUNT(DISTINCT ef.entrega_id)
                                 FROM entrega_fases ef
                                 JOIN actividad_fases af ON af.id = ef.fase_id
                                 JOIN entregas e ON e.id = ef.entrega_id
                                WHERE e.estudiante_id = ? AND af.fase = "indagar" AND ef.completada = 1', [$estudianteId], 0);
    if ($indagacion >= 10) otorgar_insignia($estudianteId, 'indagador');
}

/** Resumen de avance de un estudiante. */
function resumen_estudiante(int $id): array {
    return fila('SELECT
            COUNT(*) AS total,
            SUM(estado = "pendiente")   AS pendientes,
            SUM(estado = "en_progreso") AS en_progreso,
            SUM(estado = "entregada")   AS entregadas,
            SUM(estado = "revisada")    AS revisadas,
            SUM(estado = "rehacer")     AS rehacer,
            ROUND(AVG(progreso))        AS avance
          FROM entregas WHERE estudiante_id = ?', [$id]) ?? [];
}

/** Resumen de avance de un grupo. */
function resumen_grupo(int $grupoId): array {
    return fila('SELECT
            COUNT(e.id) AS total,
            SUM(e.estado = "entregada") AS por_revisar,
            SUM(e.estado = "revisada")  AS revisadas,
            SUM(e.estado = "rehacer")   AS rehacer,
            ROUND(AVG(e.progreso))      AS avance
          FROM entregas e
          JOIN asignaciones a ON a.id = e.asignacion_id
         WHERE a.grupo_id = ?', [$grupoId]) ?? [];
}

/** Comprueba que el docente sea dueño del grupo (o que sea administrador). */
function puede_gestionar_grupo(array $grupo): bool {
    return es('admin') || (int) $grupo['docente_id'] === uid();
}

/** Crea las entregas pendientes de una asignación para todo el grupo. */
function sembrar_entregas(int $asignacionId, int $grupoId): int {
    $n = 0;
    foreach (filas('SELECT estudiante_id FROM grupo_estudiantes WHERE grupo_id = ? AND estado = "activo"', [$grupoId]) as $e) {
        if (!valor('SELECT id FROM entregas WHERE asignacion_id = ? AND estudiante_id = ?', [$asignacionId, $e['estudiante_id']])) {
            insertar('entregas', [
                'asignacion_id' => $asignacionId,
                'estudiante_id' => $e['estudiante_id'],
                'estado'        => 'pendiente',
            ]);
            $n++;
        }
    }
    return $n;
}

/** Devuelve la actividad completa con sus fases, recursos y rúbrica. */
function actividad_completa(int $id): ?array {
    $a = fila('SELECT a.*, n.nombre AS nivel_nombre, n.codigo AS nivel_codigo, n.grado, n.programa_ib, n.color
                 FROM actividades a JOIN niveles n ON n.id = a.nivel_id WHERE a.id = ?', [$id]);
    if (!$a) return null;
    $a['fases']    = filas('SELECT * FROM actividad_fases WHERE actividad_id = ? ORDER BY orden', [$id]);
    $a['recursos'] = filas('SELECT * FROM actividad_recursos WHERE actividad_id = ? ORDER BY id', [$id]);
    $a['rubrica']  = filas('SELECT * FROM rubrica_criterios WHERE actividad_id = ? ORDER BY criterio', [$id]);
    return $a;
}

/** Etiqueta legible de una fase del ciclo de diseño. */
function nombre_fase(string $fase): string {
    return FASES_CICLO[$fase] ?? ucfirst($fase);
}
