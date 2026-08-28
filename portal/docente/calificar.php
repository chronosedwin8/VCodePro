<?php
/**
 * Fila de revisión: entregas esperando calificación.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$mio = es('admin') ? '1=1' : 'a.docente_id = ' . (int) $u['id'];

$asignacion = get_int('asignacion');
$grupoF     = get_int('grupo');
$estadoF    = get('estado') ?: 'entregada';

$where = [$mio];
$params = [];
if (in_array($estadoF, ['entregada', 'revisada', 'rehacer', 'en_progreso'], true)) {
    $where[] = 'e.estado = ?'; $params[] = $estadoF;
}
if ($asignacion) { $where[] = 'a.id = ?'; $params[] = $asignacion; }
if ($grupoF)     { $where[] = 'g.id = ?'; $params[] = $grupoF; }

$lista = filas('SELECT e.id, e.estado, e.progreso, e.entregado_en, e.intento, e.nota_letra,
                       CONCAT(u.apellidos, ", ", u.nombre) AS estudiante, u.email,
                       ac.titulo, ac.codigo, g.nombre AS grupo, a.fecha_entrega
                  FROM entregas e
                  JOIN asignaciones a ON a.id = e.asignacion_id
                  JOIN actividades ac ON ac.id = a.actividad_id
                  JOIN grupos g ON g.id = a.grupo_id
                  JOIN usuarios u ON u.id = e.estudiante_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY e.entregado_en ASC, u.apellidos', $params);

$grupos = filas('SELECT g.id, g.nombre FROM grupos g WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id']) . ' AND g.estado = "activo" ORDER BY g.nombre');

cabecera('Por calificar', [
    'titulo' => 'Fila de revisión',
    'sub'    => count($lista) . ' entrega(s) en el estado seleccionado.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Por calificar']],
]);
?>
<form class="acciones-barra" method="get">
  <select name="estado">
    <?php foreach (['entregada' => 'Esperando calificación', 'rehacer' => 'Devueltas para rehacer', 'revisada' => 'Ya calificadas', 'en_progreso' => 'En progreso'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <select name="grupo">
    <option value="0">Todos mis grupos</option>
    <?php foreach ($grupos as $g): ?>
      <option value="<?= (int) $g['id'] ?>" <?= $grupoF === (int) $g['id'] ? 'selected' : '' ?>><?= h($g['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <input type="search" data-filtra="#tabla-cola" placeholder="Buscar estudiante o actividad" class="crece">
</form>

<?php if (!$lista): ?>
  <?= vacio('Nada en la fila', 'Cuando tus estudiantes entreguen, sus trabajos aparecerán aquí en orden de llegada.') ?>
<?php else: ?>
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla" id="tabla-cola">
        <thead><tr><th>Estudiante</th><th>Actividad</th><th>Grupo</th><th>Recibida</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $e):
            $tarde = $e['entregado_en'] && strtotime($e['entregado_en']) > strtotime($e['fecha_entrega'] . ' 23:59:59');
        ?>
          <tr>
            <td><strong><?= h($e['estudiante']) ?></strong><br><span class="txt-sm txt-muted"><?= h($e['email']) ?></span></td>
            <td><?= h($e['titulo']) ?><br><span class="act-cod"><?= h($e['codigo']) ?></span></td>
            <td class="txt-sm"><?= h($e['grupo']) ?></td>
            <td class="txt-sm">
              <?= $e['entregado_en'] ? fecha($e['entregado_en'], true) : '—' ?>
              <?php if ($tarde): ?><br><span class="chip chip-rojo">fuera de plazo</span><?php endif; ?>
              <?php if ((int) $e['intento'] > 1): ?><br><span class="chip chip-gris">intento <?= (int) $e['intento'] ?></span><?php endif; ?>
            </td>
            <td><?= etiqueta_estado($e['estado']) ?><?= $e['nota_letra'] ? ' <span class="chip chip-verde">' . h($e['nota_letra']) . '</span>' : '' ?></td>
            <td class="acc"><a class="btn btn-xs" href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $e['id']) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php pie(); ?>
