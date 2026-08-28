<?php
/**
 * Mensajes recibidos desde el formulario de contacto del sitio público.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $id = post_int('id');
    if (post('accion') === 'estado' && $id) {
        actualizar('mensajes_contacto', ['estado' => post('valor')], 'id = :id', ['id' => $id]);
        flash_ok('Mensaje actualizado.');
    } elseif (post('accion') === 'eliminar' && $id) {
        borrar('mensajes_contacto', 'id = ?', [$id]);
        flash_ok('Mensaje eliminado.');
    }
    redirigir('portal/admin/mensajes.php');
}

$estadoF = get('estado');
$where = ['1=1']; $params = [];
if (in_array($estadoF, ['nuevo', 'atendido', 'archivado'], true)) { $where[] = 'estado = ?'; $params[] = $estadoF; }

$mensajes = filas('SELECT * FROM mensajes_contacto WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(estado,"nuevo","atendido","archivado"), id DESC LIMIT 300', $params);

$tot = fila('SELECT COUNT(*) AS n, SUM(estado="nuevo") AS nuevos FROM mensajes_contacto');

if (get('exportar') === 'csv') {
    descargar_csv('mensajes-contacto', ['Fecha', 'Nombre', 'Correo', 'Colegio', 'Teléfono', 'Asunto', 'Mensaje', 'Estado'],
        array_map(fn($m) => [$m['creado_en'], $m['nombre'], $m['email'], $m['colegio'], $m['telefono'], $m['asunto'], $m['mensaje'], $m['estado']], $mensajes));
}

cabecera('Mensajes del sitio', [
    'titulo' => 'Mensajes del sitio',
    'sub'    => (int) $tot['nuevos'] . ' sin atender de ' . (int) $tot['n'] . ' recibidos.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Mensajes web']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/mensajes.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<form class="acciones-barra" method="get">
  <select name="estado" onchange="this.form.submit()">
    <option value="">Todos</option>
    <?php foreach (['nuevo' => 'Nuevos', 'atendido' => 'Atendidos', 'archivado' => 'Archivados'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <input type="search" data-filtra="#tabla-msj" placeholder="Buscar por nombre, correo o colegio" class="crece">
</form>

<?php if (!$mensajes): ?>
  <?= vacio('Sin mensajes', 'Aquí llegan las solicitudes enviadas desde la página de contacto del sitio público.') ?>
<?php else: ?>
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla" id="tabla-msj">
        <thead><tr><th>Remitente</th><th>Asunto</th><th>Mensaje</th><th>Recibido</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($mensajes as $m): ?>
          <tr>
            <td>
              <strong><?= h($m['nombre']) ?></strong><br>
              <span class="txt-sm txt-muted"><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></span>
              <?php if ($m['colegio']): ?><br><span class="txt-sm txt-muted"><?= h($m['colegio']) ?></span><?php endif; ?>
              <?php if ($m['telefono']): ?><br><span class="txt-sm txt-muted"><?= h($m['telefono']) ?></span><?php endif; ?>
            </td>
            <td class="txt-sm"><?= h($m['asunto'] ?? '—') ?></td>
            <td class="txt-sm"><?= nl(corte($m['mensaje'], 220)) ?></td>
            <td class="txt-sm txt-muted"><?= fecha($m['creado_en'], true) ?></td>
            <td><?= etiqueta_estado($m['estado']) ?></td>
            <td class="acc">
              <form method="post" class="btn-fila">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='atendido'">Atendido</button>
                <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='archivado'">Archivar</button>
                <button class="btn btn-xs btn-err" name="accion" value="eliminar" data-confirmar="¿Eliminar el mensaje?">Eliminar</button>
                <input type="hidden" name="valor" value="atendido">
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php pie(); ?>
