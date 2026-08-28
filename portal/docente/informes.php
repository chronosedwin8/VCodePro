<?php
/**
 * Informes del docente: desempeño por criterio, por actividad y por grupo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$mio = es('admin') ? '1=1' : 'a.docente_id = ' . (int) $u['id'];

$grupoF = get_int('grupo');
$cond   = $grupoF ? ' AND g.id = ' . $grupoF : '';

$grupos = filas('SELECT g.id, g.nombre FROM grupos g
                  WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id']) . '
               ORDER BY g.nombre');

$porCriterio = filas("SELECT rc.criterio, rc.nombre, ROUND(AVG(c.puntaje),2) AS promedio,
                             MAX(rc.maximo) AS maximo, COUNT(*) AS evaluaciones
                        FROM calificaciones c
                        JOIN rubrica_criterios rc ON rc.id = c.criterio_id
                        JOIN entregas e ON e.id = c.entrega_id
                        JOIN asignaciones a ON a.id = e.asignacion_id
                        JOIN grupos g ON g.id = a.grupo_id
                       WHERE $mio $cond
                    GROUP BY rc.criterio, rc.nombre ORDER BY rc.criterio");

$porActividad = filas("SELECT ac.codigo, ac.titulo, g.nombre AS grupo,
                              COUNT(e.id) AS entregas,
                              SUM(e.estado = 'revisada') AS calificadas,
                              ROUND(AVG(e.progreso)) AS avance,
                              ROUND(AVG(e.nota_final),1) AS puntos
                         FROM asignaciones a
                         JOIN actividades ac ON ac.id = a.actividad_id
                         JOIN grupos g ON g.id = a.grupo_id
                    LEFT JOIN entregas e ON e.asignacion_id = a.id
                        WHERE $mio $cond
                     GROUP BY a.id, ac.codigo, ac.titulo, g.nombre
                     ORDER BY a.fecha_entrega DESC");

$porGrupo = filas("SELECT g.id, g.nombre, n.grado,
                          COUNT(DISTINCT ge.estudiante_id) AS estudiantes,
                          COUNT(DISTINCT a.id) AS asignaciones,
                          ROUND(AVG(e.progreso)) AS avance,
                          SUM(e.estado = 'entregada') AS por_revisar
                     FROM grupos g
                     JOIN niveles n ON n.id = g.nivel_id
                LEFT JOIN grupo_estudiantes ge ON ge.grupo_id = g.id AND ge.estado = 'activo'
                LEFT JOIN asignaciones a ON a.grupo_id = g.id
                LEFT JOIN entregas e ON e.asignacion_id = a.id
                    WHERE " . (es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id']) . "
                 GROUP BY g.id, g.nombre, n.grado ORDER BY g.nombre");

$puntualidad = fila("SELECT
        SUM(e.entregado_en IS NOT NULL AND DATE(e.entregado_en) <= a.fecha_entrega) AS a_tiempo,
        SUM(e.entregado_en IS NOT NULL AND DATE(e.entregado_en) >  a.fecha_entrega) AS tarde,
        SUM(e.entregado_en IS NULL AND a.fecha_entrega < CURDATE())                 AS sin_entregar
      FROM entregas e
      JOIN asignaciones a ON a.id = e.asignacion_id
      JOIN grupos g ON g.id = a.grupo_id
     WHERE $mio $cond");

if (get('exportar') === 'csv') {
    descargar_csv('informe-actividades',
        ['Código', 'Actividad', 'Grupo', 'Entregas', 'Calificadas', 'Avance %', 'Puntos promedio'],
        array_map(fn($r) => [$r['codigo'], $r['titulo'], $r['grupo'], (int) $r['entregas'],
                             (int) $r['calificadas'], (int) $r['avance'], $r['puntos'] ?? ''], $porActividad));
}

cabecera('Informes', [
    'titulo' => 'Informes del aula',
    'sub'    => 'Desempeño por criterio del programa, por actividad y por grupo.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Informes']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/informes.php?exportar=csv' . ($grupoF ? '&grupo=' . $grupoF : '')) . '">Exportar CSV</a>'
                . '<button class="btn btn-ghost no-print" onclick="window.print()">Imprimir</button>',
]);
?>
<form class="acciones-barra no-print" method="get">
  <select name="grupo" onchange="this.form.submit()">
    <option value="0">Todos mis grupos</option>
    <?php foreach ($grupos as $g): ?>
      <option value="<?= (int) $g['id'] ?>" <?= $grupoF === (int) $g['id'] ? 'selected' : '' ?>><?= h($g['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-sm" type="submit">Filtrar</button></noscript>
</form>

<div class="rejilla rej-3 mb-2">
  <?= metrica('Entregas a tiempo', (int) ($puntualidad['a_tiempo'] ?? 0), null, 'ok') ?>
  <?= metrica('Fuera de plazo', (int) ($puntualidad['tarde'] ?? 0), null, 'warn') ?>
  <?= metrica('Vencidas sin entregar', (int) ($puntualidad['sin_entregar'] ?? 0), null, 'err') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h"><h2>Desempeño por actividad</h2></div>
      <?php if (!$porActividad): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Todavía no hay actividades asignadas.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Actividad</th><th>Grupo</th><th class="num">Entregas</th><th style="min-width:130px">Avance</th><th class="num">Puntos</th></tr></thead>
            <tbody>
            <?php foreach ($porActividad as $r): ?>
              <tr>
                <td><strong><?= h($r['titulo']) ?></strong><br><span class="act-cod"><?= h($r['codigo']) ?></span></td>
                <td class="txt-sm"><?= h($r['grupo']) ?></td>
                <td class="num"><?= (int) $r['calificadas'] ?>/<?= (int) $r['entregas'] ?></td>
                <td><?= barra((int) ($r['avance'] ?? 0)) ?><span class="txt-sm txt-muted"><?= (int) ($r['avance'] ?? 0) ?>%</span></td>
                <td class="num"><?= $r['puntos'] !== null ? (float) $r['puntos'] : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel panel-plano">
      <div class="panel-h"><h2>Resumen por grupo</h2></div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Grupo</th><th>Nivel</th><th class="num">Estudiantes</th><th class="num">Actividades</th><th style="min-width:130px">Avance</th><th class="num">Por revisar</th></tr></thead>
          <tbody>
          <?php foreach ($porGrupo as $r): ?>
            <tr>
              <td><a href="<?= url('portal/docente/grupo.php?id=' . (int) $r['id']) ?>"><strong><?= h($r['nombre']) ?></strong></a></td>
              <td class="txt-sm"><?= h($r['grado']) ?></td>
              <td class="num"><?= (int) $r['estudiantes'] ?></td>
              <td class="num"><?= (int) $r['asignaciones'] ?></td>
              <td><?= barra((int) ($r['avance'] ?? 0)) ?></td>
              <td class="num"><?= (int) $r['por_revisar'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Promedio por criterio</h3></div>
      <?php if (!$porCriterio): ?>
        <p class="txt-muted mb-0">Aparecerá cuando califiques la primera entrega.</p>
      <?php else: ?>
        <?php foreach ($porCriterio as $c): ?>
          <div style="margin-bottom:1rem">
            <p class="mb-0"><strong><?= h($c['criterio']) ?>.</strong> <?= h($c['nombre']) ?></p>
            <?= barra(porcentaje((float) $c['promedio'], (float) $c['maximo']),
                      $c['promedio'] >= $c['maximo'] * 0.75 ? 'ok' : ($c['promedio'] >= $c['maximo'] * 0.5 ? '' : 'warn')) ?>
            <span class="txt-sm txt-muted"><?= (float) $c['promedio'] ?> de <?= (int) $c['maximo'] ?> · <?= (int) $c['evaluaciones'] ?> evaluaciones</span>
          </div>
        <?php endforeach; ?>
        <p class="txt-sm txt-muted mb-0">El criterio más bajo indica dónde conviene reforzar la enseñanza, no solo dónde bajar la nota.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php pie(); ?>
