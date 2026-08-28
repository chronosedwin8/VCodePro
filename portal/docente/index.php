<?php
/**
 * Panel del docente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');

$grupos = filas('SELECT g.*, n.nombre AS nivel, n.grado, n.color,
                        (SELECT COUNT(*) FROM grupo_estudiantes ge WHERE ge.grupo_id = g.id AND ge.estado = "activo") AS estudiantes,
                        (SELECT COUNT(*) FROM asignaciones a WHERE a.grupo_id = g.id) AS asignaciones
                   FROM grupos g JOIN niveles n ON n.id = g.nivel_id
                  WHERE g.docente_id = ? AND g.estado = "activo"
               ORDER BY g.nombre', [$u['id']]);

$porRevisar = filas('SELECT e.id, e.entregado_en, e.intento,
                            CONCAT(es.nombre, " ", es.apellidos) AS estudiante,
                            ac.codigo, ac.titulo, g.nombre AS grupo, a.fecha_entrega
                       FROM entregas e
                       JOIN asignaciones a ON a.id = e.asignacion_id
                       JOIN actividades ac ON ac.id = a.actividad_id
                       JOIN grupos g ON g.id = a.grupo_id
                       JOIN usuarios es ON es.id = e.estudiante_id
                      WHERE a.docente_id = ? AND e.estado = "entregada"
                   ORDER BY e.entregado_en ASC LIMIT 8', [$u['id']]);

$tot = fila('SELECT COUNT(DISTINCT ge.estudiante_id) AS estudiantes
               FROM grupo_estudiantes ge JOIN grupos g ON g.id = ge.grupo_id
              WHERE g.docente_id = ? AND ge.estado = "activo"', [$u['id']]);

$entregasTot = fila('SELECT COUNT(*) AS total,
                            SUM(e.estado = "entregada") AS por_revisar,
                            SUM(e.estado = "revisada") AS revisadas,
                            SUM(e.estado = "rehacer") AS rehacer,
                            ROUND(AVG(e.progreso)) AS avance
                       FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
                      WHERE a.docente_id = ?', [$u['id']]);

$vencen = filas('SELECT a.id, a.fecha_entrega, ac.titulo, ac.codigo, g.nombre AS grupo,
                        (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id AND e.estado IN ("pendiente","en_progreso")) AS sin_entregar
                   FROM asignaciones a
                   JOIN actividades ac ON ac.id = a.actividad_id
                   JOIN grupos g ON g.id = a.grupo_id
                  WHERE a.docente_id = ? AND a.estado = "abierta" AND a.fecha_entrega >= CURDATE() - INTERVAL 7 DAY
               ORDER BY a.fecha_entrega LIMIT 6', [$u['id']]);

cabecera('Panel del docente', [
    'titulo'   => 'Buen día, ' . $u['nombre'],
    'sub'      => 'Estado del aula: entregas por revisar, avance de los grupos y fechas próximas.',
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/banco.php') . '">Banco de actividades</a>'
                . '<a class="btn" href="' . url('portal/docente/grupos.php?nuevo=1') . '">Crear grupo</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Grupos activos', count($grupos)) ?>
  <?= metrica('Estudiantes', (int) ($tot['estudiantes'] ?? 0)) ?>
  <?= metrica('Por revisar', (int) ($entregasTot['por_revisar'] ?? 0), 'entregas esperando calificación', 'warn') ?>
  <?= metrica('Avance promedio', ((int) ($entregasTot['avance'] ?? 0)) . '%', null, 'brand') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Entregas por revisar</h2>
        <a class="txt-sm" href="<?= url('portal/docente/calificar.php') ?>">Ver la fila completa</a>
      </div>
      <?php if (!$porRevisar): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">No hay entregas pendientes de calificación. Buen momento para preparar la siguiente unidad.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Estudiante</th><th>Actividad</th><th>Recibida</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($porRevisar as $p): ?>
              <tr>
                <td><strong><?= h($p['estudiante']) ?></strong><br><span class="txt-sm txt-muted"><?= h($p['grupo']) ?></span></td>
                <td><?= h($p['titulo']) ?><br><span class="act-cod"><?= h($p['codigo']) ?></span><?= (int) $p['intento'] > 1 ? ' <span class="chip chip-ambar">intento ' . (int) $p['intento'] . '</span>' : '' ?></td>
                <td class="txt-sm txt-muted"><?= fecha_rel($p['entregado_en']) ?></td>
                <td class="acc"><a class="btn btn-xs" href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $p['id']) ?>">Calificar</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h2>Mis grupos</h2><a class="txt-sm" href="<?= url('portal/docente/grupos.php') ?>">Administrar</a></div>
      <?php if (!$grupos): ?>
        <?= vacio('Todavía no tienes grupos', 'Crea el primero y comparte el código con tus estudiantes.',
              '<a class="btn" href="' . url('portal/docente/grupos.php?nuevo=1') . '">Crear grupo</a>') ?>
      <?php else: ?>
        <div class="rejilla rej-2">
          <?php foreach ($grupos as $g): $r = resumen_grupo((int) $g['id']); ?>
            <article class="act-tarjeta">
              <div class="act-meta">
                <span class="chip chip-gris"><?= h($g['grado']) ?></span>
                <?php if ($g['modo_examen']): ?><span class="chip chip-ambar">Modo examen</span><?php endif; ?>
              </div>
              <h3><a href="<?= url('portal/docente/grupo.php?id=' . (int) $g['id']) ?>"><?= h($g['nombre']) ?></a></h3>
              <p><?= (int) $g['estudiantes'] ?> estudiantes · <?= (int) $g['asignaciones'] ?> actividades asignadas</p>
              <?= barra((int) ($r['avance'] ?? 0)) ?>
              <div class="act-meta">
                <span class="txt-sm txt-muted">Avance <?= (int) ($r['avance'] ?? 0) ?>%</span>
                <?php if ((int) ($r['por_revisar'] ?? 0) > 0): ?>
                  <span class="chip chip-ambar"><?= (int) $r['por_revisar'] ?> por revisar</span>
                <?php endif; ?>
                <span class="act-cod" data-copiar="<?= h($g['codigo']) ?>" title="Copiar código"><?= h($g['codigo']) ?></span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Fechas próximas</h3></div>
      <?php if (!$vencen): ?>
        <p class="txt-muted mb-0">No hay entregas programadas.</p>
      <?php else: ?>
        <ul class="linea">
          <?php foreach ($vencen as $v): $d = dias_para($v['fecha_entrega']); ?>
            <li>
              <h4><?= h(corte($v['titulo'], 46)) ?></h4>
              <time><?= h($v['grupo']) ?> · <?= fecha($v['fecha_entrega']) ?></time>
              <p>
                <?= (int) $v['sin_entregar'] ?> sin entregar ·
                <?= $d < 0 ? 'venció hace ' . abs($d) . ' d' : ($d === 0 ? 'vence hoy' : 'faltan ' . $d . ' d') ?>
              </p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Resumen de entregas</h3></div>
      <dl class="dl">
        <dt>Totales</dt><dd><?= (int) ($entregasTot['total'] ?? 0) ?></dd>
        <dt>Calificadas</dt><dd><?= (int) ($entregasTot['revisadas'] ?? 0) ?></dd>
        <dt>Por revisar</dt><dd><?= (int) ($entregasTot['por_revisar'] ?? 0) ?></dd>
        <dt>Para rehacer</dt><dd><?= (int) ($entregasTot['rehacer'] ?? 0) ?></dd>
      </dl>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= url('portal/docente/informes.php') ?>">Ver informes</a>
    </div>
  </aside>
</div>
<?php pie(); ?>
