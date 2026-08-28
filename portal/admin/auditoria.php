<?php
/**
 * Registro de auditoría del portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'purgar') {
        $dias = max(7, post_int('dias'));
        $n = q('DELETE FROM auditoria WHERE creado_en < DATE_SUB(NOW(), INTERVAL ? DAY)', [$dias])->rowCount();
        auditar('auditoria_purgada', null, null, "$n registros anteriores a $dias días");
        flash_ok("Se eliminaron $n registro(s) antiguos.");
    }
    redirigir('portal/admin/auditoria.php');
}

$accionF = get('accion_f');
$usuarioF = get_int('usuario');
$buscar = get('q');
$pagina = max(1, get_int('p') ?: 1);
$porPagina = 60;

$where = ['1=1']; $params = [];
if ($accionF !== '')  { $where[] = 'a.accion = ?';     $params[] = $accionF; }
if ($usuarioF)        { $where[] = 'a.usuario_id = ?'; $params[] = $usuarioF; }
if ($buscar !== '')   { $where[] = '(a.detalle LIKE ? OR a.entidad LIKE ? OR a.ip LIKE ?)'; array_push($params, "%$buscar%", "%$buscar%", "%$buscar%"); }

$total = (int) valor('SELECT COUNT(*) FROM auditoria a WHERE ' . implode(' AND ', $where), $params, 0);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$registros = filas('SELECT a.*, CONCAT(u.nombre, " ", u.apellidos) AS usuario, u.rol, u.email
                      FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
                     WHERE ' . implode(' AND ', $where) . '
                  ORDER BY a.id DESC
                     LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina), $params);

$acciones = filas('SELECT accion, COUNT(*) AS n FROM auditoria GROUP BY accion ORDER BY n DESC');
$usuarios = filas('SELECT u.id, u.nombre, u.apellidos FROM usuarios u
                    WHERE EXISTS (SELECT 1 FROM auditoria a WHERE a.usuario_id = u.id)
                 ORDER BY u.apellidos');

if (get('exportar') === 'csv') {
    $todo = filas('SELECT a.creado_en, a.accion, a.entidad, a.entidad_id, a.detalle, a.ip,
                          CONCAT(u.nombre, " ", u.apellidos) AS usuario
                     FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
                    WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 5000', $params);
    descargar_csv('auditoria', ['Fecha', 'Acción', 'Entidad', 'ID', 'Detalle', 'IP', 'Usuario'],
        array_map(fn($r) => array_values($r), $todo));
}

cabecera('Auditoría', [
    'titulo' => 'Auditoría',
    'sub'    => number_format($total, 0, ',', '.') . ' registro(s) con el filtro actual.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Auditoría']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/auditoria.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<form class="acciones-barra" method="get">
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar en el detalle o la IP" class="crece">
  <select name="accion_f">
    <option value="">Todas las acciones</option>
    <?php foreach ($acciones as $a): ?>
      <option value="<?= h($a['accion']) ?>" <?= $accionF === $a['accion'] ? 'selected' : '' ?>><?= h($a['accion']) ?> (<?= (int) $a['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="usuario">
    <option value="0">Todos los usuarios</option>
    <?php foreach ($usuarios as $x): ?>
      <option value="<?= (int) $x['id'] ?>" <?= $usuarioF === (int) $x['id'] ? 'selected' : '' ?>><?= h(trim($x['apellidos'] . ', ' . $x['nombre'])) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/admin/auditoria.php') ?>">Limpiar</a>
</form>

<div class="panel panel-plano">
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Acción</th><th>Usuario</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($registros as $r): ?>
        <tr>
          <td class="txt-sm txt-muted"><?= fecha($r['creado_en'], true) ?></td>
          <td><span class="mono txt-sm"><?= h($r['accion']) ?></span></td>
          <td class="txt-sm">
            <?= h($r['usuario'] ?? 'sistema') ?>
            <?php if ($r['rol']): ?><br><span class="chip chip-gris"><?= h(ROLES[$r['rol']]) ?></span><?php endif; ?>
          </td>
          <td class="txt-sm txt-muted"><?= h($r['entidad'] ?? '—') ?><?= $r['entidad_id'] ? ' #' . (int) $r['entidad_id'] : '' ?></td>
          <td class="txt-sm"><?= h($r['detalle'] ?? '') ?></td>
          <td class="txt-sm txt-muted mono"><?= h($r['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$registros): ?><tr><td colspan="6" class="txt-muted">Sin registros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($paginas > 1): ?>
<nav class="pag" aria-label="Paginación">
  <?php
    $qs = fn($p) => url('portal/admin/auditoria.php?p=' . $p
        . ($accionF !== '' ? '&accion_f=' . urlencode($accionF) : '')
        . ($usuarioF ? '&usuario=' . $usuarioF : '')
        . ($buscar !== '' ? '&q=' . urlencode($buscar) : ''));
    $desde = max(1, $pagina - 3); $hasta = min($paginas, $pagina + 3);
  ?>
  <?php if ($pagina > 1): ?><a href="<?= $qs($pagina - 1) ?>">Anterior</a><?php endif; ?>
  <?php for ($i = $desde; $i <= $hasta; $i++): ?>
    <?php if ($i === $pagina): ?><span class="on"><?= $i ?></span><?php else: ?><a href="<?= $qs($i) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($pagina < $paginas): ?><a href="<?= $qs($pagina + 1) ?>">Siguiente</a><?php endif; ?>
</nav>
<?php endif; ?>

<form method="post" class="panel mt-3">
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="purgar">
  <div class="panel-h"><h3>Mantenimiento</h3><p>Conserva el registro reciente y elimina el histórico antiguo.</p></div>
  <div class="campo-fila">
    <div class="campo">
      <label for="dias">Eliminar registros con más de</label>
      <input type="number" id="dias" name="dias" value="180" min="7" max="3650"> <span class="pista">días de antigüedad</span>
    </div>
  </div>
  <button class="btn btn-err" data-confirmar="¿Eliminar los registros de auditoría anteriores a ese periodo?">Purgar histórico</button>
</form>
<?php pie(); ?>
