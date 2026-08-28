<?php
/**
 * Informes institucionales: currículo, uso del portal y desempeño.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

$porNivel = filas('SELECT n.grado, n.nombre, n.programa_ib,
                          (SELECT COUNT(*) FROM actividades a WHERE a.nivel_id = n.id) AS actividades,
                          (SELECT COUNT(*) FROM grupos g WHERE g.nivel_id = n.id) AS grupos,
                          (SELECT COUNT(DISTINCT ge.estudiante_id) FROM grupos g
                             JOIN grupo_estudiantes ge ON ge.grupo_id = g.id
                            WHERE g.nivel_id = n.id AND ge.estado = "activo") AS estudiantes,
                          (SELECT COUNT(*) FROM grupos g JOIN asignaciones a2 ON a2.grupo_id = g.id
                            WHERE g.nivel_id = n.id) AS asignaciones,
                          (SELECT ROUND(AVG(e.progreso)) FROM grupos g
                             JOIN asignaciones a2 ON a2.grupo_id = g.id
                             JOIN entregas e ON e.asignacion_id = a2.id
                            WHERE g.nivel_id = n.id) AS avance,
                          (SELECT ROUND(AVG(e.nota_letra),1) FROM grupos g
                             JOIN asignaciones a2 ON a2.grupo_id = g.id
                             JOIN entregas e ON e.asignacion_id = a2.id
                            WHERE g.nivel_id = n.id AND e.nota_letra IS NOT NULL) AS nota
                     FROM niveles n ORDER BY n.orden');

$porDocente = filas('SELECT CONCAT(u.nombre, " ", u.apellidos) AS docente, u.email,
                            COUNT(DISTINCT g.id) AS grupos,
                            COUNT(DISTINCT ge.estudiante_id) AS estudiantes,
                            COUNT(DISTINCT a.id) AS asignaciones,
                            SUM(e.estado = "entregada") AS por_revisar,
                            SUM(e.estado = "revisada") AS calificadas
                       FROM usuarios u
                  LEFT JOIN grupos g ON g.docente_id = u.id AND g.estado = "activo"
                  LEFT JOIN grupo_estudiantes ge ON ge.grupo_id = g.id AND ge.estado = "activo"
                  LEFT JOIN asignaciones a ON a.grupo_id = g.id
                  LEFT JOIN entregas e ON e.asignacion_id = a.id
                      WHERE u.rol = "docente"
                   GROUP BY u.id, u.nombre, u.apellidos, u.email
                   ORDER BY grupos DESC, u.apellidos');

$porCriterio = filas('SELECT rc.criterio, rc.nombre, ROUND(AVG(c.puntaje),2) AS promedio,
                             MAX(rc.maximo) AS maximo, COUNT(*) AS evaluaciones
                        FROM calificaciones c JOIN rubrica_criterios rc ON rc.id = c.criterio_id
                    GROUP BY rc.criterio, rc.nombre ORDER BY rc.criterio');

$masUsadas = filas('SELECT ac.codigo, ac.titulo, n.grado, COUNT(a.id) AS veces,
                           ROUND(AVG(e.progreso)) AS avance
                      FROM asignaciones a
                      JOIN actividades ac ON ac.id = a.actividad_id
                      JOIN niveles n ON n.id = ac.nivel_id
                 LEFT JOIN entregas e ON e.asignacion_id = a.id
                  GROUP BY ac.id, ac.codigo, ac.titulo, n.grado
                  ORDER BY veces DESC, avance DESC LIMIT 10');

$uso = fila('SELECT
        (SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso >= CURDATE() - INTERVAL 7 DAY)  AS activos_semana,
        (SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso IS NULL)                        AS nunca_entraron,
        (SELECT COUNT(*) FROM bitacora)                                                    AS bitacoras,
        (SELECT COALESCE(SUM(minutos),0) FROM bitacora)                                    AS minutos,
        (SELECT COUNT(*) FROM entregas WHERE entregado_en IS NOT NULL)                     AS entregas,
        (SELECT COUNT(*) FROM comentarios)                                                 AS comentarios');

if (get('exportar') === 'csv') {
    descargar_csv('informe-niveles',
        ['Grado', 'Programa', 'Actividades', 'Grupos', 'Estudiantes', 'Asignaciones', 'Avance %', 'Nota media'],
        array_map(fn($n) => [$n['grado'], $n['programa_ib'], (int) $n['actividades'], (int) $n['grupos'],
                             (int) $n['estudiantes'], (int) $n['asignaciones'], (int) $n['avance'], $n['nota'] ?? ''], $porNivel));
}

cabecera('Informes', [
    'titulo' => 'Informes institucionales',
    'sub'    => 'Cobertura del currículo, uso del portal y desempeño por criterio.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Informes']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/informes.php?exportar=csv') . '">Exportar CSV</a>'
                . '<button class="btn btn-ghost no-print" onclick="window.print()">Imprimir</button>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Activos esta semana', (int) $uso['activos_semana'], 'usuarios con ingreso', 'brand') ?>
  <?= metrica('Nunca han entrado', (int) $uso['nunca_entraron'], null, 'warn') ?>
  <?= metrica('Entregas registradas', (int) $uso['entregas']) ?>
  <?= metrica('Horas de bitácora', round(((int) $uso['minutos']) / 60, 1) . ' h', (int) $uso['bitacoras'] . ' entradas') ?>
</div>

<div class="panel panel-plano">
  <div class="panel-h"><h2>Cobertura por nivel</h2></div>
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Nivel</th><th>Programa</th><th class="num">Actividades</th><th class="num">Grupos</th><th class="num">Estudiantes</th><th class="num">Asignadas</th><th style="min-width:130px">Avance</th><th class="num">Nota media</th></tr></thead>
      <tbody>
      <?php foreach ($porNivel as $n): ?>
        <tr>
          <td><strong><?= h($n['grado']) ?></strong><br><span class="txt-sm txt-muted"><?= h(corte($n['nombre'], 32)) ?></span></td>
          <td class="txt-sm"><?= h($n['programa_ib']) ?></td>
          <td class="num"><?= (int) $n['actividades'] ?></td>
          <td class="num"><?= (int) $n['grupos'] ?></td>
          <td class="num"><?= (int) $n['estudiantes'] ?></td>
          <td class="num"><?= (int) $n['asignaciones'] ?></td>
          <td><?= barra((int) ($n['avance'] ?? 0)) ?><span class="txt-sm txt-muted"><?= (int) ($n['avance'] ?? 0) ?>%</span></td>
          <td class="num"><?= $n['nota'] !== null ? (float) $n['nota'] : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h"><h2>Carga y avance por docente</h2></div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Docente</th><th class="num">Grupos</th><th class="num">Estudiantes</th><th class="num">Asignaciones</th><th class="num">Por revisar</th><th class="num">Calificadas</th></tr></thead>
          <tbody>
          <?php foreach ($porDocente as $d): ?>
            <tr>
              <td><strong><?= h($d['docente']) ?></strong><br><span class="txt-sm txt-muted"><?= h($d['email']) ?></span></td>
              <td class="num"><?= (int) $d['grupos'] ?></td>
              <td class="num"><?= (int) $d['estudiantes'] ?></td>
              <td class="num"><?= (int) $d['asignaciones'] ?></td>
              <td class="num"><?= (int) $d['por_revisar'] ? '<span class="chip chip-ambar">' . (int) $d['por_revisar'] . '</span>' : '0' ?></td>
              <td class="num"><?= (int) $d['calificadas'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel panel-plano">
      <div class="panel-h"><h2>Actividades más usadas</h2></div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Actividad</th><th>Nivel</th><th class="num">Grupos</th><th style="min-width:130px">Avance medio</th></tr></thead>
          <tbody>
          <?php foreach ($masUsadas as $m): ?>
            <tr>
              <td><strong><?= h($m['titulo']) ?></strong><br><span class="act-cod"><?= h($m['codigo']) ?></span></td>
              <td class="txt-sm"><?= h($m['grado']) ?></td>
              <td class="num"><?= (int) $m['veces'] ?></td>
              <td><?= barra((int) ($m['avance'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$masUsadas): ?><tr><td colspan="4" class="txt-muted">Sin asignaciones registradas.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Desempeño por criterio</h3></div>
      <?php if (!$porCriterio): ?>
        <p class="txt-muted mb-0">Sin calificaciones registradas todavía.</p>
      <?php else: ?>
        <?php foreach ($porCriterio as $c): ?>
          <div style="margin-bottom:1rem">
            <p class="mb-0"><strong><?= h($c['criterio']) ?>.</strong> <?= h($c['nombre']) ?></p>
            <?= barra(porcentaje((float) $c['promedio'], (float) $c['maximo']),
                      $c['promedio'] >= $c['maximo'] * 0.75 ? 'ok' : ($c['promedio'] >= $c['maximo'] * 0.5 ? '' : 'warn')) ?>
            <span class="txt-sm txt-muted"><?= (float) $c['promedio'] ?> de <?= (int) $c['maximo'] ?> · <?= (int) $c['evaluaciones'] ?> evaluaciones</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Interacción</h3></div>
      <dl class="dl">
        <dt>Entradas de bitácora</dt><dd><?= (int) $uso['bitacoras'] ?></dd>
        <dt>Comentarios</dt><dd><?= (int) $uso['comentarios'] ?></dd>
        <dt>Entregas enviadas</dt><dd><?= (int) $uso['entregas'] ?></dd>
      </dl>
      <p class="txt-sm txt-muted mb-0">La bitácora es el mejor indicador de que el ciclo de diseño se está usando de verdad y no solo al final.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
