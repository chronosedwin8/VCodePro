<?php
/**
 * Panel del estudiante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');
revisar_insignias($u['id']);

$r = resumen_estudiante($u['id']);

$proximas = filas('SELECT e.id AS entrega_id, e.estado, e.progreso, a.fecha_entrega,
                          ac.titulo, ac.codigo, ac.sesiones, g.nombre AS grupo
                     FROM entregas e
                     JOIN asignaciones a ON a.id = e.asignacion_id
                     JOIN actividades ac ON ac.id = a.actividad_id
                     JOIN grupos g ON g.id = a.grupo_id
                    WHERE e.estudiante_id = ? AND a.estado = "abierta"
                      AND e.estado IN ("pendiente","en_progreso","rehacer")
                 ORDER BY a.fecha_entrega ASC LIMIT 6', [$u['id']]);

$calificadas = filas('SELECT e.id, e.nota_letra, e.nota_final, ac.titulo, ac.codigo, a.fecha_entrega
                        FROM entregas e
                        JOIN asignaciones a ON a.id = e.asignacion_id
                        JOIN actividades ac ON ac.id = a.actividad_id
                       WHERE e.estudiante_id = ? AND e.estado = "revisada"
                    ORDER BY e.actualizado_en DESC LIMIT 5', [$u['id']]);

$grupos = filas('SELECT g.*, n.nombre AS nivel, n.grado, n.color,
                        CONCAT(d.nombre, " ", d.apellidos) AS docente
                   FROM grupo_estudiantes ge
                   JOIN grupos g ON g.id = ge.grupo_id
                   JOIN niveles n ON n.id = g.nivel_id
                   JOIN usuarios d ON d.id = g.docente_id
                  WHERE ge.estudiante_id = ? AND ge.estado = "activo"', [$u['id']]);

$bitacora = filas('SELECT * FROM bitacora WHERE estudiante_id = ? ORDER BY id DESC LIMIT 4', [$u['id']]);
$insignias = (int) valor('SELECT COUNT(*) FROM usuario_insignias WHERE usuario_id = ?', [$u['id']], 0);
$puntos = (int) valor('SELECT COALESCE(SUM(i.puntos),0) FROM usuario_insignias ui
                         JOIN insignias i ON i.id = ui.insignia_id WHERE ui.usuario_id = ?', [$u['id']], 0);

cabecera('Mi panel', [
    'titulo' => 'Hola, ' . $u['nombre'],
    'sub'    => 'Tu trabajo del periodo, ordenado por fecha de entrega.',
    'acciones' => '<a class="btn" href="' . url('portal/estudiante/actividades.php') . '">Ver todas mis actividades</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Actividades asignadas', (int) ($r['total'] ?? 0)) ?>
  <?= metrica('Por trabajar', (int) ($r['pendientes'] ?? 0) + (int) ($r['en_progreso'] ?? 0), 'incluye las que están en curso', 'brand') ?>
  <?= metrica('Calificadas', (int) ($r['revisadas'] ?? 0), null, 'ok') ?>
  <?= metrica('Avance promedio', ((int) ($r['avance'] ?? 0)) . '%') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel">
      <div class="panel-h">
        <h2>Próximas entregas</h2>
        <p><?= count($proximas) ?> actividad(es) abierta(s)</p>
      </div>
      <?php if (!$proximas): ?>
        <?= vacio('Nada pendiente por ahora', 'Cuando tu docente asigne una actividad nueva aparecerá aquí.') ?>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Actividad</th><th>Entrega</th><th>Avance</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($proximas as $p):
                $dias = dias_para($p['fecha_entrega']);
                $tono = $dias < 0 ? 'err' : ($dias <= 3 ? 'warn' : '');
            ?>
              <tr>
                <td>
                  <strong><?= h($p['titulo']) ?></strong><br>
                  <span class="act-cod"><?= h($p['codigo']) ?></span>
                  <span class="txt-sm txt-muted"> · <?= h($p['grupo']) ?></span>
                </td>
                <td>
                  <?= fecha($p['fecha_entrega']) ?><br>
                  <span class="txt-sm <?= $tono === 'err' ? 'chip chip-rojo' : ($tono === 'warn' ? 'chip chip-ambar' : 'txt-muted') ?>">
                    <?= $dias < 0 ? 'venció hace ' . abs($dias) . ' d' : ($dias === 0 ? 'hoy' : 'en ' . $dias . ' días') ?>
                  </span>
                </td>
                <td style="min-width:120px">
                  <?= barra((int) $p['progreso'], $tono) ?>
                  <span class="txt-sm txt-muted"><?= (int) $p['progreso'] ?>%</span>
                </td>
                <td class="acc">
                  <a class="btn btn-xs" href="<?= url('portal/estudiante/actividad.php?e=' . (int) $p['entrega_id']) ?>">
                    <?= $p['estado'] === 'pendiente' ? 'Empezar' : 'Continuar' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h">
        <h2>Últimas calificaciones</h2>
        <a class="txt-sm" href="<?= url('portal/estudiante/calificaciones.php') ?>">Ver todas</a>
      </div>
      <?php if (!$calificadas): ?>
        <p class="txt-muted mb-0">Todavía no tienes actividades calificadas.</p>
      <?php else: ?>
        <table class="tabla tabla-mini">
          <tbody>
          <?php foreach ($calificadas as $c): ?>
            <tr>
              <td><strong><?= h($c['titulo']) ?></strong><br><span class="act-cod"><?= h($c['codigo']) ?></span></td>
              <td class="num"><span class="chip chip-verde">Nota <?= h($c['nota_letra']) ?></span></td>
              <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/estudiante/actividad.php?e=' . (int) $c['id']) ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Mis grupos</h3></div>
      <?php foreach ($grupos as $g): ?>
        <p class="mb-0" style="padding:.55rem 0;border-bottom:1px solid var(--border-soft)">
          <strong><?= h($g['nombre']) ?></strong><br>
          <span class="txt-sm txt-muted"><?= h($g["grado"]) ?> · <?= h($g["nivel"]) ?> · <?= h($g['docente']) ?></span>
        </p>
      <?php endforeach; ?>
      <?php if (!$grupos): ?><p class="txt-muted mb-0">No estás matriculado en ningún grupo.</p><?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Progreso</h3></div>
      <div class="rejilla" style="gap:12px">
        <?= metrica('Insignias', $insignias) ?>
        <?= metrica('Puntos acumulados', $puntos, null, 'brand') ?>
      </div>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= url('portal/estudiante/insignias.php') ?>">Ver mis insignias</a>
    </div>

    <div class="panel">
      <div class="panel-h">
        <h3>Bitácora reciente</h3>
        <a class="txt-sm" href="<?= url('portal/estudiante/bitacora.php') ?>">Abrir</a>
      </div>
      <?php if (!$bitacora): ?>
        <p class="txt-muted mb-0">Registra lo que haces en cada sesión: es la evidencia del criterio C.</p>
      <?php else: ?>
        <ul class="linea">
          <?php foreach ($bitacora as $b): ?>
            <li>
              <h4><?= h($b['titulo']) ?></h4>
              <time><?= h(nombre_fase($b['fase'])) ?> · <?= fecha_rel($b['creado_en']) ?></time>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php pie(); ?>
