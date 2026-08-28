<?php
/**
 * Banco de actividades: administración del currículo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $id = post_int('id');
    $accion = post('accion');

    if ($accion === 'publicar' && $id) {
        $v = (int) valor('SELECT publicada FROM actividades WHERE id = ?', [$id], 0);
        actualizar('actividades', ['publicada' => $v ? 0 : 1], 'id = :id', ['id' => $id]);
        flash_ok($v ? 'Actividad retirada del banco.' : 'Actividad publicada.');
    }

    if ($accion === 'duplicar' && $id) {
        $a = fila('SELECT * FROM actividades WHERE id = ?', [$id]);
        if ($a) {
            $nuevoCodigo = $a['codigo'] . '-C' . random_int(10, 99);
            unset($a['id'], $a['creado_en'], $a['actualizado_en']);
            $a['codigo'] = $nuevoCodigo;
            $a['titulo'] = $a['titulo'] . ' (copia)';
            $a['publicada'] = 0;
            $a['creado_por'] = $u['id'];
            $nid = insertar('actividades', $a);
            foreach (filas('SELECT * FROM actividad_fases WHERE actividad_id = ?', [$id]) as $f) {
                unset($f['id']); $f['actividad_id'] = $nid; insertar('actividad_fases', $f);
            }
            foreach (filas('SELECT * FROM actividad_recursos WHERE actividad_id = ?', [$id]) as $r) {
                unset($r['id']); $r['actividad_id'] = $nid; insertar('actividad_recursos', $r);
            }
            foreach (filas('SELECT * FROM rubrica_criterios WHERE actividad_id = ?', [$id]) as $c) {
                unset($c['id']); $c['actividad_id'] = $nid; insertar('rubrica_criterios', $c);
            }
            auditar('actividad_duplicada', 'actividades', $nid, $nuevoCodigo);
            flash_ok('Actividad duplicada como ' . $nuevoCodigo . '.');
            redirigir('portal/admin/actividad.php?id=' . $nid);
        }
    }

    if ($accion === 'eliminar' && $id) {
        $usos = (int) valor('SELECT COUNT(*) FROM asignaciones WHERE actividad_id = ?', [$id], 0);
        if ($usos > 0) {
            flash_err("No se puede eliminar: la actividad está asignada en $usos grupo(s). Retírala del banco en su lugar.");
        } else {
            borrar('actividades', 'id = ?', [$id]);
            auditar('actividad_eliminada', 'actividades', $id);
            flash_ok('Actividad eliminada.');
        }
    }
    $nivelQ = get_int('nivel');
    redirigir('portal/admin/actividades.php' . ($nivelQ ? '?nivel=' . $nivelQ : ''));
}

$nivelF  = get_int('nivel');
$estadoF = get('estado');
$buscar  = get('q');

$where = ['1=1']; $params = [];
if ($nivelF) { $where[] = 'a.nivel_id = ?'; $params[] = $nivelF; }
if ($estadoF === 'publicadas')   $where[] = 'a.publicada = 1';
if ($estadoF === 'borradores')   $where[] = 'a.publicada = 0';
if ($buscar !== '') {
    $where[] = '(a.titulo LIKE ? OR a.codigo LIKE ? OR a.resumen LIKE ?)';
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}

$actividades = filas('SELECT a.*, n.grado, n.nombre AS nivel,
                             (SELECT COUNT(*) FROM actividad_fases f WHERE f.actividad_id = a.id) AS fases,
                             (SELECT COUNT(*) FROM rubrica_criterios r WHERE r.actividad_id = a.id) AS criterios,
                             (SELECT COUNT(*) FROM asignaciones s WHERE s.actividad_id = a.id) AS asignada
                        FROM actividades a JOIN niveles n ON n.id = a.nivel_id
                       WHERE ' . implode(' AND ', $where) . '
                    ORDER BY n.orden, a.orden', $params);

$niveles = filas('SELECT n.*, (SELECT COUNT(*) FROM actividades a WHERE a.nivel_id = n.id) AS cuantas FROM niveles n ORDER BY orden');

if (get('exportar') === 'csv') {
    descargar_csv('banco-actividades',
        ['Código', 'Nivel', 'Título', 'Criterios', 'Contexto global', 'Lenguaje', 'Dificultad', 'Sesiones', 'Publicada'],
        array_map(fn($a) => [$a['codigo'], $a['grado'], $a['titulo'], $a['criterios_ib'], $a['contexto_global'],
                             $a['lenguaje'], $a['dificultad'], (int) $a['sesiones'], $a['publicada'] ? 'sí' : 'no'], $actividades));
}

cabecera('Actividades', [
    'titulo' => 'Banco de actividades',
    'sub'    => count($actividades) . ' actividad(es) con el filtro actual.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Actividades']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/actividades.php?exportar=csv') . '">Exportar CSV</a>'
                . '<a class="btn" href="' . url('portal/admin/actividad.php?id=0') . '">Nueva actividad</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?php foreach (array_slice($niveles, 0, 4) as $n): ?>
    <?= metrica($n['grado'] . ' · ' . $n['programa_ib'], (int) $n['cuantas'], 'actividades') ?>
  <?php endforeach; ?>
</div>

<form class="acciones-barra" method="get">
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar por código, título o resumen" class="crece">
  <select name="nivel">
    <option value="0">Todos los niveles</option>
    <?php foreach ($niveles as $n): ?>
      <option value="<?= (int) $n['id'] ?>" <?= $nivelF === (int) $n['id'] ? 'selected' : '' ?>><?= h($n['grado'] . ' · ' . $n['programa_ib']) ?> (<?= (int) $n['cuantas'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="estado">
    <option value="">Todas</option>
    <option value="publicadas" <?= $estadoF === 'publicadas' ? 'selected' : '' ?>>Publicadas</option>
    <option value="borradores" <?= $estadoF === 'borradores' ? 'selected' : '' ?>>Borradores</option>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/admin/actividades.php') ?>">Limpiar</a>
</form>

<div class="panel panel-plano">
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Actividad</th><th>Nivel</th><th>Criterios</th><th class="num">Estructura</th><th class="num">Uso</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($actividades as $a): ?>
        <tr>
          <td>
            <strong><a href="<?= url('portal/admin/actividad.php?id=' . (int) $a['id']) ?>"><?= h($a['titulo']) ?></a></strong><br>
            <span class="act-cod"><?= h($a['codigo']) ?></span>
            <span class="txt-sm txt-muted">· <?= h($a['lenguaje']) ?> · <?= (int) $a['sesiones'] ?> sesiones</span>
          </td>
          <td class="txt-sm"><?= h($a['grado']) ?></td>
          <td><span class="chip chip-crit"><?= h($a['criterios_ib']) ?></span></td>
          <td class="num txt-sm"><?= (int) $a['fases'] ?> fases<br><?= (int) $a['criterios'] ?> criterios</td>
          <td class="num"><?= (int) $a['asignada'] ?> grupo(s)</td>
          <td><?= $a['publicada'] ? '<span class="chip chip-verde">Publicada</span>' : '<span class="chip chip-gris">Borrador</span>' ?></td>
          <td class="acc">
            <form method="post" class="btn-fila">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/actividad.php?id=' . (int) $a['id']) ?>">Editar</a>
              <button class="btn btn-xs btn-ghost" name="accion" value="publicar"><?= $a['publicada'] ? 'Retirar' : 'Publicar' ?></button>
              <button class="btn btn-xs btn-ghost" name="accion" value="duplicar">Duplicar</button>
              <button class="btn btn-xs btn-err" name="accion" value="eliminar"
                      data-confirmar="¿Eliminar la actividad del banco?">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pie(); ?>
