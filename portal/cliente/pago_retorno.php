<?php
/**
 * Retorno desde Mercado Pago (back_urls de Checkout Pro).
 *
 * Mercado Pago añade a esta URL parámetros como payment_id, status y
 * collection_status. **No se confía en ellos**: cualquiera puede escribirlos a
 * mano en la barra de direcciones. Lo único que decide es la consulta a la API
 * con nuestra propia referencia.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pagos_api.php';

$u = exigir_rol('cliente', 'admin');

$referencia = get('ref') ?: get('external_reference');
$pago = $referencia !== ''
    ? fila('SELECT * FROM pagos WHERE referencia = ? AND cliente_id = ?', [$referencia, $u['id']])
    : null;

if (!$pago) {
    flash_err('No encontramos ese pago en tu cuenta.');
    redirigir('portal/cliente/facturas.php');
}

// La verdad se consulta a la API, nunca se lee de la URL.
[$okConciliacion, $mensajeConciliacion] = conciliar_por_referencia($referencia);

$pago    = fila('SELECT * FROM pagos WHERE id = ?', [$pago['id']]);
$factura = $pago['factura_id'] ? fila('SELECT * FROM facturas WHERE id = ?', [$pago['factura_id']]) : null;
$estado  = (string) $pago['estado'];

cabecera('Resultado del pago', [
    'titulo' => 'Resultado del pago',
    'sub'    => $factura ? 'Factura ' . $factura['numero'] . ' · ' . $factura['concepto'] : '',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Facturas', 'portal/cliente/facturas.php'], ['Resultado']],
]);
?>

<?php if ($estado === 'aprobado'): ?>
  <div class="aviso aviso-ok">
    <div>
      <strong>Pago aprobado.</strong>
      <?= $factura ? 'La factura ' . h($factura['numero']) . ' quedó saldada y tu licencia está activa.' : '' ?>
    </div>
  </div>
<?php elseif ($estado === 'en_proceso' || $estado === 'pendiente'): ?>
  <div class="aviso aviso-warn">
    <div>
      <strong>El pago está en proceso.</strong>
      Mercado Pago todavía no lo ha acreditado; si pagaste en efectivo o por PSE puede tardar.
      No hace falta que hagas nada: te avisamos en cuanto se confirme.
    </div>
  </div>
<?php elseif ($estado === 'rechazado'): ?>
  <div class="aviso aviso-err">
    <div>
      <strong>El pago no se completó.</strong>
      <?= h(mp_motivo((string) $pago['estado_detalle'])) ?>
      Puedes intentarlo de nuevo con otro medio de pago.
    </div>
  </div>
<?php else: ?>
  <div class="aviso aviso-info"><div><?= h($mensajeConciliacion) ?></div></div>
<?php endif; ?>

<div class="rejilla rej-lat">
  <div class="panel">
    <div class="panel-h"><h2>Detalle de la operación</h2></div>
    <dl class="dl">
      <dt>Referencia</dt><dd class="mono"><?= h($pago['referencia']) ?></dd>
      <?php if ($pago['pago_externo']): ?>
        <dt>Pago en Mercado Pago</dt><dd class="mono"><?= h($pago['pago_externo']) ?></dd>
      <?php endif; ?>
      <dt>Monto</dt><dd><strong><?= moneda((float) $pago['monto'], $pago['moneda']) ?></strong></dd>
      <?php if ($pago['metodo']): ?><dt>Medio de pago</dt><dd><?= h($pago['metodo']) ?></dd><?php endif; ?>
      <dt>Estado</dt>
      <dd>
        <?php if ($estado === 'aprobado'): ?><span class="chip chip-verde">Aprobado</span>
        <?php elseif ($estado === 'rechazado'): ?><span class="chip chip-rojo">Rechazado</span>
        <?php else: ?><span class="chip chip-ambar"><?= h(ucfirst(str_replace('_', ' ', $estado))) ?></span><?php endif; ?>
      </dd>
      <dt>Entorno</dt><dd><?= $pago['entorno'] === 'produccion' ? 'Producción' : 'Prueba' ?></dd>
    </dl>

    <div class="form-acc">
      <?php if ($factura && $estado === 'aprobado'): ?>
        <a class="btn" href="<?= url('portal/cliente/facturas.php?ver=' . (int) $factura['id']) ?>">Ver el comprobante</a>
      <?php elseif ($factura && $estado === 'rechazado'): ?>
        <a class="btn" href="<?= url('portal/cliente/pagar.php?factura=' . (int) $factura['id']) ?>">Intentar de nuevo</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= url('portal/cliente/facturas.php') ?>">Mis facturas</a>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Cómo confirmamos el pago</h3></div>
      <p class="txt-sm txt-muted mb-0">
        Este resultado no sale de la dirección que ves en el navegador: el portal vuelve a
        preguntarle a Mercado Pago por tu referencia antes de mostrarte nada, y la confirmación
        definitiva llega por notificación directa a nuestro servidor. Si aquí dice «en proceso»
        y luego se acredita, la factura se actualiza sola.
      </p>
    </div>
    <?php if (!$okConciliacion): ?>
      <div class="panel">
        <div class="panel-h"><h3>Aviso</h3></div>
        <p class="txt-sm txt-muted mb-0">No pudimos consultar la pasarela en este momento
          (<?= h($mensajeConciliacion) ?>). El estado se corregirá solo cuando llegue la notificación.</p>
      </div>
    <?php endif; ?>
  </aside>
</div>
<?php pie(); ?>
