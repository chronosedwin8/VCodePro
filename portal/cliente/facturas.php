<?php
/**
 * Facturas del cliente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('cliente', 'admin');

$facturas = filas('SELECT f.*, l.clave FROM facturas f
                LEFT JOIN licencias l ON l.id = f.licencia_id
                    WHERE f.cliente_id = ? ORDER BY f.emitida_en DESC', [$u['id']]);

$ver = get_int('ver');
$detalle = $ver ? fila('SELECT f.*, l.clave, l.plan, l.cupo FROM facturas f
                     LEFT JOIN licencias l ON l.id = f.licencia_id
                         WHERE f.id = ? AND f.cliente_id = ?', [$ver, $u['id']]) : null;

$tot = [
    'pagado'    => array_sum(array_map(fn($f) => $f['estado'] === 'pagada' ? (float) $f['monto'] : 0, $facturas)),
    'pendiente' => array_sum(array_map(fn($f) => $f['estado'] === 'pendiente' ? (float) $f['monto'] : 0, $facturas)),
    'vencido'   => array_sum(array_map(fn($f) => $f['estado'] === 'vencida' ? (float) $f['monto'] : 0, $facturas)),
];

if (get('exportar') === 'csv') {
    descargar_csv('mis-facturas', ['Número', 'Concepto', 'Monto', 'Moneda', 'Estado', 'Emitida', 'Vence'],
        array_map(fn($f) => [$f['numero'], $f['concepto'], $f['monto'], $f['moneda'], $f['estado'], $f['emitida_en'], $f['vence_en']], $facturas));
}

cabecera('Facturas', [
    'titulo' => 'Mis facturas',
    'sub'    => 'Historial de cobros de licencias y servicios.',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Facturas']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/cliente/facturas.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<div class="rejilla rej-3 mb-2">
  <?= metrica('Pagado', moneda($tot['pagado']), null, 'ok') ?>
  <?= metrica('Pendiente', moneda($tot['pendiente']), null, 'warn') ?>
  <?= metrica('Vencido', moneda($tot['vencido']), null, 'err') ?>
</div>

<?php if ($detalle): ?>
<div class="panel">
  <div class="panel-h">
    <div>
      <h2>Factura <?= h($detalle['numero']) ?></h2>
      <p>Emitida el <?= fecha($detalle['emitida_en']) ?> · vence el <?= fecha($detalle['vence_en']) ?></p>
    </div>
    <div class="btn-fila no-print">
      <button class="btn btn-ghost btn-sm" onclick="window.print()">Imprimir</button>
      <a class="btn btn-ghost btn-sm" href="<?= url('portal/cliente/facturas.php') ?>">Cerrar</a>
    </div>
  </div>
  <dl class="dl">
    <dt>Cliente</dt><dd><?= h(nombre_completo($u)) ?> · <?= h($u['colegio_nombre'] ?? '') ?></dd>
    <dt>Concepto</dt><dd><?= h($detalle['concepto']) ?></dd>
    <?php if ($detalle['clave']): ?>
      <dt>Licencia</dt><dd class="mono"><?= h($detalle['clave']) ?> · <?= h(ucfirst($detalle['plan'])) ?> · <?= (int) $detalle['cupo'] ?> puestos</dd>
    <?php endif; ?>
    <dt>Monto</dt><dd><strong><?= moneda((float) $detalle['monto'], $detalle['moneda']) ?></strong></dd>
    <dt>Estado</dt><dd><?= etiqueta_estado($detalle['estado']) ?></dd>
  </dl>
  <p class="txt-sm txt-muted mt-2 mb-0">
    Valores finales sin impuestos añadidos: VCodePro tiene sede en Alemania y la licencia se vende como servicio digital internacional.
  </p>
</div>
<?php endif; ?>

<div class="panel panel-plano">
  <div class="panel-h"><h2>Historial</h2></div>
  <?php if (!$facturas): ?>
    <div style="padding:20px"><p class="txt-muted mb-0">No hay facturas registradas para tu cuenta.</p></div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Número</th><th>Concepto</th><th>Licencia</th><th class="num">Monto</th><th>Vence</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($facturas as $f): ?>
          <tr>
            <td class="mono txt-sm"><?= h($f['numero']) ?></td>
            <td class="txt-sm"><?= h($f['concepto']) ?></td>
            <td class="txt-sm txt-muted"><?= h($f['clave'] ?? '—') ?></td>
            <td class="num"><?= moneda((float) $f['monto'], $f['moneda']) ?></td>
            <td class="txt-sm"><?= fecha($f['vence_en']) ?></td>
            <td><?= etiqueta_estado($f['estado']) ?></td>
            <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/cliente/facturas.php?ver=' . (int) $f['id']) ?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php pie(); ?>
