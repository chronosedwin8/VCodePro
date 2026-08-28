<?php
/**
 * Facturación de las licencias y servicios.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');
    $id = post_int('id');

    if ($accion === 'crear') {
        $numero = post('numero') ?: ('VCP-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));
        if (valor('SELECT id FROM facturas WHERE numero = ?', [$numero])) {
            flash_err('Ya existe una factura con ese número.');
        } elseif (!post_int('cliente_id')) {
            flash_err('Selecciona el cliente.');
        } else {
            $fid = insertar('facturas', [
                'cliente_id'  => post_int('cliente_id'),
                'licencia_id' => post_int('licencia_id') ?: null,
                'numero'      => $numero,
                'concepto'    => post('concepto'),
                'monto'       => (float) post('monto', '0'),
                'moneda'      => mb_strtoupper(post('moneda') ?: 'COP'),
                'estado'      => in_array(post('estado'), ['pagada', 'pendiente', 'vencida', 'anulada'], true) ? post('estado') : 'pendiente',
                'emitida_en'  => post('emitida_en') ?: date('Y-m-d'),
                'vence_en'    => post('vence_en') ?: date('Y-m-d', strtotime('+30 days')),
            ]);
            auditar('factura_creada', 'facturas', $fid, $numero);
            notificar(post_int('cliente_id'), 'Nueva factura ' . $numero, post('concepto'), 'portal/cliente/facturas.php');
            flash_ok('Factura ' . $numero . ' registrada.');
        }
    }

    if ($accion === 'estado' && $id) {
        actualizar('facturas', ['estado' => post('valor')], 'id = :id', ['id' => $id]);
        auditar('factura_estado', 'facturas', $id, post('valor'));
        flash_ok('Estado de la factura actualizado.');
    }

    if ($accion === 'eliminar' && $id) {
        borrar('facturas', 'id = ?', [$id]);
        flash_ok('Factura eliminada.');
    }
    redirigir('portal/admin/facturas.php');
}

q('UPDATE facturas SET estado = "vencida" WHERE estado = "pendiente" AND vence_en < CURDATE()');

$estadoF = get('estado');
$where = ['1=1']; $params = [];
if (in_array($estadoF, ['pagada', 'pendiente', 'vencida', 'anulada'], true)) { $where[] = 'f.estado = ?'; $params[] = $estadoF; }

$facturas = filas('SELECT f.*, CONCAT(u.nombre, " ", u.apellidos) AS cliente, u.email, l.clave
                     FROM facturas f
                     JOIN usuarios u ON u.id = f.cliente_id
                LEFT JOIN licencias l ON l.id = f.licencia_id
                    WHERE ' . implode(' AND ', $where) . '
                 ORDER BY f.emitida_en DESC', $params);

$tot = fila('SELECT COALESCE(SUM(CASE WHEN estado = "pagada" THEN monto ELSE 0 END),0) AS pagado,
                    COALESCE(SUM(CASE WHEN estado = "pendiente" THEN monto ELSE 0 END),0) AS pendiente,
                    COALESCE(SUM(CASE WHEN estado = "vencida" THEN monto ELSE 0 END),0) AS vencido,
                    COUNT(*) AS n FROM facturas');

$clientes  = filas('SELECT id, nombre, apellidos, email FROM usuarios WHERE rol IN ("cliente","admin") ORDER BY apellidos');
$licencias = filas('SELECT id, clave, plan FROM licencias ORDER BY clave');

if (get('exportar') === 'csv') {
    descargar_csv('facturas', ['Número', 'Cliente', 'Concepto', 'Monto', 'Moneda', 'Estado', 'Emitida', 'Vence'],
        array_map(fn($f) => [$f['numero'], $f['cliente'], $f['concepto'], $f['monto'], $f['moneda'], $f['estado'], $f['emitida_en'], $f['vence_en']], $facturas));
}

cabecera('Facturación', [
    'titulo' => 'Facturación',
    'sub'    => 'Estado de cobro de licencias y servicios de formación.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Facturación']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/facturas.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Facturas', (int) $tot['n']) ?>
  <?= metrica('Recaudado', moneda((float) $tot['pagado']), null, 'ok') ?>
  <?= metrica('Por cobrar', moneda((float) $tot['pendiente']), null, 'warn') ?>
  <?= metrica('Vencido', moneda((float) $tot['vencido']), null, 'err') ?>
</div>

<div class="rejilla rej-lat">
  <div class="panel panel-plano">
    <div class="panel-h">
      <h2>Documentos</h2>
      <form method="get" class="btn-fila">
        <select name="estado" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach (['pagada' => 'Pagadas', 'pendiente' => 'Pendientes', 'vencida' => 'Vencidas', 'anulada' => 'Anuladas'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Número</th><th>Cliente</th><th>Concepto</th><th class="num">Monto</th><th>Vence</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($facturas as $f): ?>
          <tr>
            <td><code class="mono"><?= h($f['numero']) ?></code><?= $f['clave'] ? '<br><span class="txt-sm txt-muted">' . h($f['clave']) . '</span>' : '' ?></td>
            <td class="txt-sm"><?= h($f['cliente']) ?><br><span class="txt-muted"><?= h($f['email']) ?></span></td>
            <td class="txt-sm"><?= h($f['concepto']) ?></td>
            <td class="num"><?= moneda((float) $f['monto'], $f['moneda']) ?></td>
            <td class="txt-sm"><?= fecha($f['vence_en']) ?></td>
            <td><?= etiqueta_estado($f['estado']) ?></td>
            <td class="acc">
              <form method="post" class="btn-fila">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <?php if ($f['estado'] !== 'pagada'): ?>
                  <button class="btn btn-xs btn-ok" name="accion" value="estado" onclick="this.form.valor.value='pagada'">Marcar pagada</button>
                <?php else: ?>
                  <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='pendiente'">Reabrir</button>
                <?php endif; ?>
                <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='anulada'" data-confirmar="¿Anular la factura?">Anular</button>
                <button class="btn btn-xs btn-err" name="accion" value="eliminar" data-confirmar="¿Eliminar el documento?">Eliminar</button>
                <input type="hidden" name="valor" value="pagada">
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="panel-h"><h3>Nueva factura</h3></div>
      <div class="campo">
        <label for="cliente_id">Cliente</label>
        <select id="cliente_id" name="cliente_id" required>
          <?php foreach ($clientes as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= h(trim($c['nombre'] . ' ' . $c['apellidos'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="licencia_id">Licencia asociada</label>
        <select id="licencia_id" name="licencia_id">
          <option value="0">Ninguna</option>
          <?php foreach ($licencias as $l): ?><option value="<?= (int) $l['id'] ?>"><?= h($l['clave']) ?> · <?= h($l['plan']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="numero">Número</label><input type="text" id="numero" name="numero" placeholder="Se genera automáticamente"></div>
      <div class="campo"><label for="concepto">Concepto</label><input type="text" id="concepto" name="concepto" required></div>
      <div class="campo-fila">
        <div class="campo"><label for="monto">Monto</label><input type="number" id="monto" name="monto" step="0.01" min="0" required></div>
        <div class="campo"><label for="moneda">Moneda</label><input type="text" id="moneda" name="moneda" value="COP" maxlength="3"></div>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="emitida_en">Emitida</label><input type="date" id="emitida_en" name="emitida_en" value="<?= date('Y-m-d') ?>"></div>
        <div class="campo"><label for="vence_en">Vence</label><input type="date" id="vence_en" name="vence_en" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
      </div>
      <div class="campo">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
          <option value="pendiente">Pendiente</option>
          <option value="pagada">Pagada</option>
        </select>
      </div>
      <button class="btn btn-block" type="submit">Registrar factura</button>
    </form>
  </aside>
</div>
<?php pie(); ?>
