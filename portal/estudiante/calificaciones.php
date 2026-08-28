<?php
/**
 * Calificaciones del estudiante por criterio IB.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');

$entregas = filas('SELECT e.id, e.estado, e.nota_final, e.nota_letra, e.entregado_en,
                          ac.codigo, ac.titulo, ac.criterios_ib, a.fecha_entrega, g.nombre AS grupo
                     FROM entregas e
                     JOIN asignaciones a ON a.id = e.asignacion_id
                     JOIN actividades ac ON ac.id = a.actividad_id
                     JOIN grupos g ON g.id = a.grupo_id
                    WHERE e.estudiante_id = ?
                 ORDER BY a.fecha_entrega DESC', [$u['id']]);

// Promedio por criterio (A, B, C, D, E) en todas las entregas calificadas.
$porCriterio = filas('SELECT rc.criterio, rc.nombre,
                             ROUND(AVG(c.puntaje), 1) AS promedio,
                             MAX(rc.maximo) AS maximo,
                             COUNT(*) AS veces
                        FROM calificaciones c
                        JOIN rubrica_criterios rc ON rc.id = c.criterio_id
                        JOIN entregas e ON e.id = c.entrega_id
                       WHERE e.estudiante_id = ?
                    GROUP BY rc.criterio, rc.nombre
                    ORDER BY rc.criterio', [$u['id']]);

$calificadas = array_values(array_filter($entregas, fn($e) => $e['estado'] === 'revisada'));
$promedioNota = $calificadas
    ? round(array_sum(array_map(fn($e) => (float) $e['nota_letra'], $calificadas)) / count($calificadas), 1)
    : null;

cabecera('Calificaciones', [
    'titulo' => 'Mis calificaciones',
    'sub'    => 'Resultado por actividad y desempeño acumulado en cada criterio del programa.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Calificaciones']],
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Actividades calificadas', count($calificadas)) ?>
  <?= metrica('Nota promedio IB', $promedioNota ?? '—', 'escala de 1 a 7', 'brand') ?>
  <?= metrica('Entregas por revisar', (int) valor('SELECT COUNT(*) FROM entregas WHERE estudiante_id = ? AND estado = "entregada"', [$u['id']], 0), null, 'warn') ?>
  <?= metrica('Debo rehacer', (int) valor('SELECT COUNT(*) FROM entregas WHERE estudiante_id = ? AND estado = "rehacer"', [$u['id']], 0), null, 'err') ?>
</div>

<div class="rejilla rej-lat">
  <div class="panel panel-plano">
    <div class="panel-h"><h2>Historial de actividades</h2></div>
    <?php if (!$entregas): ?>
      <?= vacio('Todavía no tienes actividades', 'Cuando tu docente asigne trabajo aparecerá aquí.') ?>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Actividad</th><th>Grupo</th><th>Estado</th><th class="num">Puntos</th><th class="num">Nota</th><th class="acc">&nbsp;</th></tr></thead>
          <tbody>
          <?php foreach ($entregas as $e): ?>
            <tr>
              <td>
                <strong><?= h($e['titulo']) ?></strong><br>
                <span class="act-cod"><?= h($e['codigo']) ?></span>
                <span class="txt-sm txt-muted">· entrega <?= fecha($e['fecha_entrega']) ?></span>
              </td>
              <td class="txt-sm"><?= h($e['grupo']) ?></td>
              <td><?= etiqueta_estado($e['estado']) ?></td>
              <td class="num"><?= $e['nota_final'] !== null ? (float) $e['nota_final'] : '—' ?></td>
              <td class="num"><?= $e['nota_letra'] ? '<span class="chip chip-verde">' . h($e['nota_letra']) . '</span>' : '—' ?></td>
              <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/estudiante/actividad.php?e=' . (int) $e['id']) ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Desempeño por criterio</h3></div>
      <?php if (!$porCriterio): ?>
        <p class="txt-muted mb-0">Aparecerá cuando tengas la primera actividad calificada.</p>
      <?php else: ?>
        <?php foreach ($porCriterio as $c): ?>
          <div style="margin-bottom:1rem">
            <p class="mb-0"><strong><?= h($c['criterio']) ?>.</strong> <?= h($c['nombre']) ?></p>
            <?= barra(porcentaje((float) $c['promedio'], (float) $c['maximo']),
                      $c['promedio'] >= $c['maximo'] * 0.75 ? 'ok' : ($c['promedio'] >= $c['maximo'] * 0.5 ? '' : 'warn')) ?>
            <span class="txt-sm txt-muted"><?= (float) $c['promedio'] ?> de <?= (int) $c['maximo'] ?> · <?= (int) $c['veces'] ?> actividad(es)</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Cómo se traduce la nota</h3></div>
      <table class="tabla tabla-mini">
        <tbody>
          <tr><td>7</td><td class="txt-muted">90 % o más del total</td></tr>
          <tr><td>6</td><td class="txt-muted">78 % a 89 %</td></tr>
          <tr><td>5</td><td class="txt-muted">65 % a 77 %</td></tr>
          <tr><td>4</td><td class="txt-muted">50 % a 64 %</td></tr>
          <tr><td>3</td><td class="txt-muted">37 % a 49 %</td></tr>
          <tr><td>2</td><td class="txt-muted">22 % a 36 %</td></tr>
          <tr><td>1</td><td class="txt-muted">menos del 22 %</td></tr>
        </tbody>
      </table>
    </div>
  </aside>
</div>
<?php pie(); ?>
