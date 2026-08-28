<?php
/**
 * Matriz de seguimiento: estudiantes por actividad, con estado y avance.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');

$grupos = filas('SELECT g.id, g.nombre, n.grado FROM grupos g JOIN niveles n ON n.id = g.nivel_id
                  WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id']) . ' AND g.estado = "activo"
               ORDER BY g.nombre');

$grupoId = get_int('grupo') ?: (int) ($grupos[0]['id'] ?? 0);
$estudianteId = get_int('estudiante');

if (!$grupoId) {
    cabecera('Seguimiento', ['titulo' => 'Seguimiento', 'sub' => 'Necesitas al menos un grupo activo.']);
    echo vacio('Sin grupos', 'Crea un grupo para ver la matriz de avance.',
        '<a class="btn" href="' . url('portal/docente/grupos.php?nuevo=1') . '">Crear grupo</a>');
    pie(); exit;
}

$g = fila('SELECT g.*, n.grado, n.nombre AS nivel FROM grupos g JOIN niveles n ON n.id = g.nivel_id WHERE g.id = ?', [$grupoId]);
if (!$g || !puede_gestionar_grupo($g)) { flash_err('Grupo no disponible.'); redirigir('portal/docente/seguimiento.php'); }

$asignaciones = filas('SELECT a.id, ac.codigo, ac.titulo, a.fecha_entrega
                         FROM asignaciones a JOIN actividades ac ON ac.id = a.actividad_id
                        WHERE a.grupo_id = ? ORDER BY a.fecha_entrega', [$grupoId]);

$estudiantes = filas('SELECT u.id, u.nombre, u.apellidos FROM grupo_estudiantes ge
                        JOIN usuarios u ON u.id = ge.estudiante_id
                       WHERE ge.grupo_id = ? AND ge.estado = "activo"
                    ORDER BY u.apellidos, u.nombre', [$grupoId]);

$celdas = [];
foreach (filas('SELECT e.id, e.asignacion_id, e.estudiante_id, e.estado, e.progreso, e.nota_letra
                  FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
                 WHERE a.grupo_id = ?', [$grupoId]) as $c) {
    $celdas[(int) $c['estudiante_id']][(int) $c['asignacion_id']] = $c;
}

// Exportación de la matriz.
if (get('exportar') === 'csv') {
    $enc = array_merge(['Estudiante'], array_map(fn($a) => $a['codigo'], $asignaciones), ['Avance %']);
    $out = [];
    foreach ($estudiantes as $es) {
        $fila = [trim($es['apellidos'] . ', ' . $es['nombre'])];
        $suma = 0; $n = 0;
        foreach ($asignaciones as $a) {
            $c = $celdas[(int) $es['id']][(int) $a['id']] ?? null;
            $fila[] = $c ? ($c['nota_letra'] ?: $c['estado']) : '—';
            if ($c) { $suma += (int) $c['progreso']; $n++; }
        }
        $fila[] = $n ? round($suma / $n) : 0;
        $out[] = $fila;
    }
    descargar_csv('seguimiento-' . slug($g['nombre']), $enc, $out);
}

// --------------------------------------------- detalle de un estudiante ---
$detalle = null;
if ($estudianteId) {
    $detalle = fila('SELECT u.* FROM usuarios u JOIN grupo_estudiantes ge ON ge.estudiante_id = u.id
                      WHERE u.id = ? AND ge.grupo_id = ?', [$estudianteId, $grupoId]);
}

cabecera('Seguimiento', [
    'titulo' => 'Matriz de avance',
    'sub'    => $g['nombre'] . ' · ' . $g['grado'] . ' · ' . count($estudiantes) . ' estudiantes · ' . count($asignaciones) . ' actividades',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Seguimiento']],
    'ancho'  => 'full',
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/seguimiento.php?grupo=' . $grupoId . '&exportar=csv') . '">Exportar CSV</a>',
]);
?>
<form class="acciones-barra" method="get">
  <select name="grupo" onchange="this.form.submit()">
    <?php foreach ($grupos as $x): ?>
      <option value="<?= (int) $x['id'] ?>" <?= $grupoId === (int) $x['id'] ? 'selected' : '' ?>><?= h($x['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-sm" type="submit">Cambiar grupo</button></noscript>
</form>

<?php if (!$asignaciones): ?>
  <?= vacio('Este grupo no tiene actividades asignadas', 'Asigna la primera desde el banco.',
        '<a class="btn" href="' . url('portal/docente/banco.php?grupo=' . $grupoId) . '">Ir al banco</a>') ?>
<?php elseif (!$estudiantes): ?>
  <?= vacio('Este grupo no tiene estudiantes', 'Comparte el código de matrícula o agrégalos manualmente.',
        '<a class="btn" href="' . url('portal/docente/grupo.php?id=' . $grupoId) . '">Administrar el grupo</a>') ?>
<?php else: ?>
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla matriz">
        <thead>
          <tr>
            <th style="min-width:210px">Estudiante</th>
            <?php foreach ($asignaciones as $a): ?>
              <th class="rot" title="<?= h($a['titulo']) ?>"><?= h($a['codigo']) ?></th>
            <?php endforeach; ?>
            <th style="min-width:120px">Avance</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($estudiantes as $es):
            $suma = 0; $n = 0;
            foreach ($asignaciones as $a) {
                $c = $celdas[(int) $es['id']][(int) $a['id']] ?? null;
                if ($c) { $suma += (int) $c['progreso']; $n++; }
            }
            $avance = $n ? (int) round($suma / $n) : 0;
        ?>
          <tr>
            <td>
              <a href="<?= url('portal/docente/seguimiento.php?grupo=' . $grupoId . '&estudiante=' . (int) $es['id']) ?>">
                <strong><?= h(trim($es['apellidos'] . ', ' . $es['nombre'])) ?></strong>
              </a>
            </td>
            <?php foreach ($asignaciones as $a):
                $c = $celdas[(int) $es['id']][(int) $a['id']] ?? null;
            ?>
              <td class="celda">
                <?php if (!$c): ?>
                  <span class="mz mz-pendiente" title="Sin entrega creada">·</span>
                <?php else: ?>
                  <a href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $c['id']) ?>"
                     class="mz mz-<?= h($c['estado']) ?>"
                     title="<?= h($a['titulo'] . ' — ' . $c['estado'] . ' — ' . $c['progreso'] . '%') ?>">
                    <?= $c['nota_letra'] ?: (int) $c['progreso'] ?>
                  </a>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td><?= barra($avance) ?><span class="txt-sm txt-muted"><?= $avance ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="leyenda" style="padding:14px 20px">
      <span><i style="background:var(--bg-alt)"></i> Sin iniciar</span>
      <span><i style="background:var(--info-soft)"></i> En progreso</span>
      <span><i style="background:var(--warn-soft)"></i> Entregada</span>
      <span><i style="background:var(--ok-soft)"></i> Calificada (muestra la nota)</span>
      <span><i style="background:var(--err-soft)"></i> Debe rehacer</span>
    </div>
  </div>
<?php endif; ?>

<?php if ($detalle):
    $ent = filas('SELECT e.*, ac.titulo, ac.codigo, a.fecha_entrega
                    FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
                    JOIN actividades ac ON ac.id = a.actividad_id
                   WHERE a.grupo_id = ? AND e.estudiante_id = ? ORDER BY a.fecha_entrega', [$grupoId, $estudianteId]);
    $bit = filas('SELECT * FROM bitacora WHERE estudiante_id = ? ORDER BY id DESC LIMIT 8', [$estudianteId]);
?>
<div class="rejilla rej-lat mt-2">
  <div class="panel panel-plano">
    <div class="panel-h">
      <h2><?= h(nombre_completo($detalle)) ?></h2>
      <a class="txt-sm" href="<?= url('portal/docente/seguimiento.php?grupo=' . $grupoId) ?>">Cerrar detalle</a>
    </div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Actividad</th><th>Estado</th><th style="min-width:120px">Avance</th><th class="num">Nota</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($ent as $x): ?>
          <tr>
            <td><?= h($x['titulo']) ?><br><span class="act-cod"><?= h($x['codigo']) ?></span></td>
            <td><?= etiqueta_estado($x['estado']) ?></td>
            <td><?= barra((int) $x['progreso']) ?></td>
            <td class="num"><?= $x['nota_letra'] ?: '—' ?></td>
            <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $x['id']) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Bitácora reciente</h3></div>
      <?php if (!$bit): ?><p class="txt-muted mb-0">Sin registros.</p><?php else: ?>
        <ul class="linea">
          <?php foreach ($bit as $b): ?>
            <li><h4><?= h($b['titulo']) ?></h4><time><?= h(nombre_fase($b['fase'])) ?> · <?= fecha($b['creado_en']) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php endif; ?>
<?php pie(); ?>
