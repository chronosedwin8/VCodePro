<?php
/**
 * Pago de una factura con Checkout Pro.
 *
 * Esta página NO pide datos de tarjeta: muestra el resumen y envía al
 * comprador a Mercado Pago, que es quien captura y procesa el pago. Por eso la
 * integración se mantiene en el alcance PCI DSS SAQ A.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pagos_api.php';

$u = exigir_rol('cliente', 'admin');
$facturaId = get_int('factura');

$f = fila('SELECT f.*, l.clave, l.plan, l.cupo
             FROM facturas f LEFT JOIN licencias l ON l.id = f.licencia_id
            WHERE f.id = ? AND f.cliente_id = ?', [$facturaId, $u['id']]);

if (!$f) { flash_err('No encontramos esa factura en tu cuenta.'); redirigir('portal/cliente/facturas.php'); }

if ($f['estado'] === 'pagada') {
    flash_ok('La factura ' . $f['numero'] . ' ya está pagada.');
    redirigir('portal/cliente/facturas.php?ver=' . $facturaId);
}
if ($f['estado'] === 'anulada') {
    flash_err('Esa factura está anulada. Escribe a soporte si crees que es un error.');
    redirigir('portal/cliente/facturas.php');
}

// ------------------------------------------------------------------ acción --
if (es_post()) {
    exigir_csrf();
    if (!mp_configurado()) {
        flash_err('La pasarela de pagos todavía no está configurada. Avísale a la coordinación.');
        redirigir('portal/cliente/pagar.php?factura=' . $facturaId);
    }
    [$ok, $destino] = crear_preferencia($f, $u);
    if (!$ok) {
        flash_err('No se pudo iniciar el pago: ' . $destino);
        redirigir('portal/cliente/pagar.php?factura=' . $facturaId);
    }
    // Salida hacia el dominio de Mercado Pago.
    redirigir($destino);
}

$intentos = filas('SELECT * FROM pagos WHERE factura_id = ? ORDER BY id DESC LIMIT 5', [$facturaId]);

cabecera('Pagar factura', [
    'titulo' => 'Pagar ' . $f['numero'],
    'sub'    => $f['concepto'],
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Facturas', 'portal/cliente/facturas.php'], ['Pagar']],
]);
?>

<?php if (!mp_configurado()): ?>
  <div class="aviso aviso-warn">
    <div><strong>La pasarela no está configurada todavía.</strong>
      Escribe a <a href="mailto:<?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?>"><?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?></a>
      para coordinar el pago por transferencia.</div>
  </div>
<?php elseif (mp_entorno() === 'prueba'): ?>
  <div class="aviso aviso-info">
    <div><strong>Entorno de prueba.</strong> Se usará el checkout de prueba de Mercado Pago: no se cobra dinero real.</div>
  </div>
<?php endif; ?>

<div class="rejilla rej-lat">
  <div>
    <div class="panel">
      <div class="panel-h">
        <h2>Resumen del pago</h2>
        <span class="chip chip-verde">Pago protegido</span>
      </div>

      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Concepto</th><th class="num">Valor</th></tr></thead>
          <tbody>
            <tr>
              <td>
                <strong><?= h($f['concepto']) ?></strong><br>
                <span class="txt-sm txt-muted">Factura <?= h($f['numero']) ?><?= $f['clave'] ? ' · licencia ' . h($f['clave']) : '' ?></span>
              </td>
              <td class="num"><?= moneda((float) $f['monto'], $f['moneda']) ?></td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th>Total a pagar</th>
              <th class="num" style="font-size:1.15rem"><?= moneda((float) $f['monto'], $f['moneda']) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <p class="txt-sm txt-muted mt-2">
        Al continuar te llevamos al sitio seguro de Mercado Pago, donde eliges el medio de pago
        —tarjeta, PSE, efectivo o saldo— y completas la transacción. Al terminar vuelves aquí.
      </p>

      <form method="post">
        <?= csrf_campo() ?>
        <div class="form-acc">
          <button class="btn btn-lg" type="submit" <?= mp_configurado() ? '' : 'disabled' ?>>
            Continuar a Mercado Pago
          </button>
          <a class="btn btn-ghost" href="<?= url('portal/cliente/facturas.php') ?>">Cancelar</a>
        </div>
      </form>
    </div>

    <?php if ($intentos): ?>
    <div class="panel panel-plano">
      <div class="panel-h"><h2>Intentos anteriores</h2></div>
      <div class="tabla-caja">
        <table class="tabla tabla-mini">
          <thead><tr><th>Referencia</th><th>Estado</th><th>Motivo</th><th>Cuándo</th></tr></thead>
          <tbody>
          <?php foreach ($intentos as $i): ?>
            <tr>
              <td class="mono txt-sm"><?= h($i['pago_externo'] ?: $i['referencia']) ?></td>
              <td>
                <?php if ($i['estado'] === 'aprobado'): ?><span class="chip chip-verde">Aprobado</span>
                <?php elseif ($i['estado'] === 'rechazado'): ?><span class="chip chip-rojo">Rechazado</span>
                <?php else: ?><span class="chip chip-ambar"><?= h(ucfirst(str_replace('_', ' ', $i['estado']))) ?></span><?php endif; ?>
              </td>
              <td class="txt-sm txt-muted"><?= $i['estado_detalle'] ? h(mp_motivo((string) $i['estado_detalle'])) : '—' ?></td>
              <td class="txt-sm txt-muted"><?= fecha_rel($i['creado_en']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Detalle</h3></div>
      <dl class="dl">
        <dt>Factura</dt><dd class="mono"><?= h($f['numero']) ?></dd>
        <?php if ($f['clave']): ?>
          <dt>Licencia</dt><dd class="mono txt-sm"><?= h($f['clave']) ?></dd>
          <dt>Plan</dt><dd><?= h(ucfirst((string) $f['plan'])) ?> · <?= (int) $f['cupo'] ?> puestos</dd>
        <?php endif; ?>
        <dt>Emitida</dt><dd><?= fecha($f['emitida_en']) ?></dd>
        <dt>Vence</dt><dd><?= fecha($f['vence_en']) ?></dd>
      </dl>
      <p style="font-size:1.6rem;font-weight:700;color:var(--text-strong);margin:1rem 0 0">
        <?= moneda((float) $f['monto'], $f['moneda']) ?>
      </p>
      <p class="txt-sm txt-muted">Valor final, sin impuestos añadidos.</p>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Por qué es seguro</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li>Los datos de tu tarjeta se escriben <strong>en el sitio de Mercado Pago</strong>, no en este portal.</li>
        <li>VCodePro nunca ve, transmite ni almacena el número de tu tarjeta.</li>
        <li>El pago se confirma por notificación directa de Mercado Pago a nuestro servidor.</li>
        <li>Si el pago queda en revisión, te avisamos por correo cuando se acredite.</li>
      </ul>
    </div>
  </aside>
</div>
<?php pie(); ?>
