<?php
/**
 * Banco de actividades: catálogo por nivel con filtros y asignación directa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');

$grupos = filas('SELECT g.id, g.nombre, g.nivel_id, n.grado
                   FROM grupos g JOIN niveles n ON n.id = g.nivel_id
                  WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ?') . ' AND g.estado = "activo"
               ORDER BY g.nombre', es('admin') ? [] : [$u['id']]);

// ------------------------------------------------------ asignar actividad --
if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'asignar') {
        $grupoId = post_int('grupo_id');
        $actId   = post_int('actividad_id');
        $g = fila('SELECT * FROM grupos WHERE id = ?', [$grupoId]);
        $ac = fila('SELECT * FROM actividades WHERE id = ?', [$actId]);
        if (!$g || !$ac || !puede_gestionar_grupo($g)) {
            flash_err('No se pudo asignar la actividad.');
        } elseif (valor('SELECT id FROM asignaciones WHERE grupo_id = ? AND actividad_id = ?', [$grupoId, $actId])) {
            flash_err('Esa actividad ya está asignada a ' . $g['nombre'] . '.');
        } else {
            $aid = insertar('asignaciones', [
                'grupo_id'      => $grupoId,
                'actividad_id'  => $actId,
                'docente_id'    => (int) $g['docente_id'],
                'fecha_inicio'  => post('fecha_inicio') ?: date('Y-m-d'),
                'fecha_entrega' => post('fecha_entrega') ?: date('Y-m-d', strtotime('+14 days')),
                'instrucciones' => post('instrucciones') ?: null,
                'ia_permitida'  => isset($_POST['ia_permitida']) ? 1 : 0,
                'estado'        => 'abierta',
            ]);
            $n = sembrar_entregas($aid, $grupoId);
            foreach (filas('SELECT estudiante_id FROM grupo_estudiantes WHERE grupo_id = ? AND estado = "activo"', [$grupoId]) as $e) {
                notificar((int) $e['estudiante_id'], 'Nueva actividad: ' . $ac['titulo'],
                    'Entrega el ' . fecha(post('fecha_entrega') ?: date('Y-m-d', strtotime('+14 days'))) . '.',
                    'portal/estudiante/actividades.php');
            }
            auditar('actividad_asignada', 'asignaciones', $aid, $ac['codigo'] . ' → ' . $g['nombre']);
            flash_ok('Actividad asignada a ' . $g['nombre'] . ' para ' . $n . ' estudiante(s).');
            redirigir('portal/docente/asignaciones.php?id=' . $aid);
        }
    }
}

// ------------------------------------------------------------- catálogo ----
$nivelF   = get_int('nivel');
$grupoPre = get_int('grupo');
$dif      = get('dificultad');
$buscar   = get('q');
$ctx      = get('contexto');

$where = ['a.publicada = 1']; $params = [];
if ($nivelF) { $where[] = 'a.nivel_id = ?'; $params[] = $nivelF; }
if (in_array($dif, ['inicial', 'intermedio', 'avanzado'], true)) { $where[] = 'a.dificultad = ?'; $params[] = $dif; }
if ($ctx !== '') { $where[] = 'a.contexto_global = ?'; $params[] = $ctx; }
if ($buscar !== '') {
    $where[] = '(a.titulo LIKE ? OR a.resumen LIKE ? OR a.codigo LIKE ? OR a.lenguaje LIKE ?)';
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%", "%$buscar%");
}

$actividades = filas('SELECT a.*, n.grado, n.nombre AS nivel, n.color
                        FROM actividades a JOIN niveles n ON n.id = a.nivel_id
                       WHERE ' . implode(' AND ', $where) . '
                    ORDER BY n.orden, a.orden', $params);

$niveles = filas('SELECT n.*, (SELECT COUNT(*) FROM actividades a WHERE a.nivel_id = n.id) AS cuantas
                    FROM niveles n ORDER BY n.orden');

cabecera('Banco de actividades', [
    'titulo' => 'Banco de actividades',
    'sub'    => count($actividades) . ' actividades del plan de aula, con ciclo de diseño y rúbrica listos para usar.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Banco de actividades']],
]);
?>
<form class="acciones-barra" method="get">
  <?php if ($grupoPre): ?><input type="hidden" name="grupo" value="<?= $grupoPre ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar por título, código o lenguaje" class="crece">
  <select name="nivel">
    <option value="0">Todos los niveles</option>
    <?php foreach ($niveles as $n): ?>
      <option value="<?= (int) $n['id'] ?>" <?= $nivelF === (int) $n['id'] ? 'selected' : '' ?>>
        <?= h($n['grado'] . ' · ' . $n['programa_ib']) ?> (<?= (int) $n['cuantas'] ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <select name="dificultad">
    <option value="">Toda dificultad</option>
    <?php foreach (['inicial', 'intermedio', 'avanzado'] as $x): ?>
      <option value="<?= $x ?>" <?= $dif === $x ? 'selected' : '' ?>><?= ucfirst($x) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="contexto">
    <option value="">Todo contexto global</option>
    <?php foreach (CONTEXTOS_GLOBALES as $c): ?>
      <option value="<?= h($c) ?>" <?= $ctx === $c ? 'selected' : '' ?>><?= h($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/docente/banco.php') ?>">Limpiar</a>
</form>

<?php if (!$actividades): ?>
  <?= vacio('Sin resultados', 'Prueba con otros filtros o busca por el código de la actividad.') ?>
<?php else: ?>
  <div class="rejilla rej-3">
    <?php foreach ($actividades as $a): ?>
      <article class="act-tarjeta">
        <div class="act-meta">
          <span class="chip chip-gris"><?= h($a['grado']) ?></span>
          <span class="chip chip-azul"><?= h($a['dificultad']) ?></span>
          <span class="chip chip-crit"><?= h($a['criterios_ib']) ?></span>
        </div>
        <h3><a href="<?= url('portal/docente/actividad.php?id=' . (int) $a['id'] . ($grupoPre ? '&grupo=' . $grupoPre : '')) ?>"><?= h($a['titulo']) ?></a></h3>
        <p><?= h(corte($a['resumen'], 140)) ?></p>
        <div class="act-meta">
          <span class="act-cod"><?= h($a['codigo']) ?></span>
          <span class="txt-sm txt-muted">· <?= (int) $a['sesiones'] ?> sesiones · <?= h($a['lenguaje']) ?></span>
        </div>
        <div class="act-meta">
          <a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/actividad.php?id=' . (int) $a['id'] . ($grupoPre ? '&grupo=' . $grupoPre : '')) ?>">Ver y asignar</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php pie(); ?>
