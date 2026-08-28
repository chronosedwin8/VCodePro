<?php
/**
 * Listado de actividades asignadas al estudiante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');

$estado = get('estado');
$grupoF = get_int('grupo');
$buscar = get('q');

$where  = ['e.estudiante_id = ?'];
$params = [$u['id']];
if (in_array($estado, ['pendiente', 'en_progreso', 'entregada', 'revisada', 'rehacer'], true)) {
    $where[] = 'e.estado = ?';   $params[] = $estado;
}
if ($grupoF) { $where[] = 'a.grupo_id = ?'; $params[] = $grupoF; }
if ($buscar !== '') {
    $where[] = '(ac.titulo LIKE ? OR ac.codigo LIKE ? OR ac.resumen LIKE ?)';
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}

$lista = filas('SELECT e.id AS entrega_id, e.estado, e.progreso, e.nota_letra,
                       a.fecha_entrega, a.estado AS estado_asignacion,
                       ac.id AS actividad_id, ac.titulo, ac.codigo, ac.resumen, ac.dificultad,
                       ac.lenguaje, ac.sesiones, ac.contexto_global, ac.criterios_ib,
                       g.nombre AS grupo, n.grado, n.color
                  FROM entregas e
                  JOIN asignaciones a ON a.id = e.asignacion_id
                  JOIN actividades ac ON ac.id = a.actividad_id
                  JOIN grupos g ON g.id = a.grupo_id
                  JOIN niveles n ON n.id = ac.nivel_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY FIELD(e.estado, "rehacer","en_progreso","pendiente","entregada","revisada"),
                       a.fecha_entrega ASC', $params);

$grupos = filas('SELECT g.id, g.nombre FROM grupo_estudiantes ge
                   JOIN grupos g ON g.id = ge.grupo_id
                  WHERE ge.estudiante_id = ?', [$u['id']]);

cabecera('Mis actividades', [
    'titulo' => 'Mis actividades',
    'sub'    => 'Todo lo asignado en tus grupos, con el avance de cada fase del ciclo de diseño.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Mis actividades']],
]);
?>
<form class="acciones-barra" method="get">
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar por título o código" class="crece">
  <select name="estado">
    <option value="">Todos los estados</option>
    <?php foreach (['pendiente' => 'Pendientes', 'en_progreso' => 'En progreso', 'entregada' => 'Entregadas', 'revisada' => 'Calificadas', 'rehacer' => 'Debo rehacer'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <select name="grupo">
    <option value="0">Todos mis grupos</option>
    <?php foreach ($grupos as $g): ?>
      <option value="<?= (int) $g['id'] ?>" <?= $grupoF === (int) $g['id'] ? 'selected' : '' ?>><?= h($g['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/estudiante/actividades.php') ?>">Limpiar</a>
</form>

<?php if (!$lista): ?>
  <?= vacio('No hay actividades con ese filtro', 'Prueba con otro estado o revisa más adelante.') ?>
<?php else: ?>
  <div class="rejilla rej-3">
    <?php foreach ($lista as $a):
        $dias = dias_para($a['fecha_entrega']);
        $vencida = $dias < 0 && in_array($a['estado'], ['pendiente', 'en_progreso', 'rehacer'], true);
    ?>
      <article class="act-tarjeta">
        <div class="act-meta">
          <span class="chip chip-gris"><?= h($a['grado']) ?></span>
          <?= etiqueta_estado($a['estado']) ?>
          <?php if ($vencida): ?><span class="chip chip-rojo">Vencida</span><?php endif; ?>
        </div>
        <h3><a href="<?= url('portal/estudiante/actividad.php?e=' . (int) $a['entrega_id']) ?>"><?= h($a['titulo']) ?></a></h3>
        <p><?= h(corte($a['resumen'], 130)) ?></p>
        <?= barra((int) $a['progreso'], $a['estado'] === 'revisada' ? 'ok' : ($vencida ? 'err' : '')) ?>
        <div class="act-meta">
          <span class="act-cod"><?= h($a['codigo']) ?></span>
          <span class="txt-sm txt-muted">· <?= (int) $a['sesiones'] ?> sesiones · <?= h($a['lenguaje']) ?></span>
        </div>
        <div class="act-meta">
          <span class="txt-sm txt-muted">Entrega <?= fecha($a['fecha_entrega']) ?></span>
          <?php if ($a['nota_letra']): ?><span class="chip chip-verde">Nota <?= h($a['nota_letra']) ?></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php pie(); ?>
