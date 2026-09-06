<?php
/**
 * Pago de una factura con Checkout API.
 *
 * El formulario usa el SDK oficial de Mercado Pago: la tarjeta se tokeniza en
 * el navegador y a este servidor solo llega el token, nunca el número.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pagos_api.php';

$u = exigir_rol('cliente', 'admin');
$facturaId = get_int('factura');

$f = fila('SELECT f.*, l.clave, l.plan, l.cupo, l.vence_en AS licencia_vence
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

$resultado = null;

if (es_post()) {
    exigir_csrf();
    if (!mp_configurado()) {
        flash_err('La pasarela de pagos todavía no está configurada. Avísale a la coordinación.');
        redirigir('portal/cliente/pagar.php?factura=' . $facturaId);
    }

    $form = [
        'token'             => post('token'),
        'payment_method_id' => post('payment_method_id'),
        'issuer_id'         => post('issuer_id'),
        'installments'      => post_int('installments') ?: 1,
        'email'             => post('email') ?: $u['email'],
        'doc_tipo'          => post('doc_tipo'),
        'doc_numero'        => post('doc_numero'),
    ];

    if ($form['token'] === '' || $form['payment_method_id'] === '') {
        flash_err('No se pudo leer la tarjeta. Revisa los datos y vuelve a intentarlo.');
        redirigir('portal/cliente/pagar.php?factura=' . $facturaId);
    }

    [$aprobado, $mensaje, $pagoId] = cobrar_factura($f, $form, (int) $u['id']);
    iniciar_sesion();
    $_SESSION['pago_resultado'] = ['aprobado' => $aprobado, 'mensaje' => $mensaje, 'pago' => $pagoId];
    redirigir('portal/cliente/pagar.php?factura=' . $facturaId . '&resultado=1');
}

if (get('resultado') === '1') {
    iniciar_sesion();
    $resultado = $_SESSION['pago_resultado'] ?? null;
    unset($_SESSION['pago_resultado']);
}

$intentos = filas('SELECT * FROM pagos WHERE factura_id = ? ORDER BY id DESC LIMIT 5', [$facturaId]);

cabecera('Pagar factura', [
    'titulo' => 'Pagar ' . $f['numero'],
    'sub'    => $f['concepto'],
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Facturas', 'portal/cliente/facturas.php'], ['Pagar']],
]);
?>

<?php if ($resultado): ?>
  <?php if ($resultado['aprobado']): ?>
    <div class="aviso aviso-ok">
      <div><strong>Pago aprobado.</strong> <?= h($resultado['mensaje']) ?>
        La factura <?= h($f['numero']) ?> quedó saldada y tu licencia está activa.</div>
    </div>
    <p><a class="btn" href="<?= url('portal/cliente/facturas.php?ver=' . $facturaId) ?>">Ver el comprobante</a></p>
    <?php pie(); exit; ?>
  <?php else: ?>
    <div class="aviso aviso-err"><div><strong>El pago no se completó.</strong> <?= h($resultado['mensaje']) ?></div></div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!mp_configurado()): ?>
  <div class="aviso aviso-warn">
    <div><strong>La pasarela no está configurada todavía.</strong>
      Escribe a <a href="mailto:<?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?>"><?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?></a>
      para coordinar el pago por transferencia.</div>
  </div>
<?php elseif (mp_entorno() === 'prueba'): ?>
  <div class="aviso aviso-info">
    <div><strong>Entorno de prueba.</strong> Estás usando credenciales de prueba: no se cobra dinero real.</div>
  </div>
<?php endif; ?>

<div class="rejilla rej-lat">
  <div>
    <div class="panel">
      <div class="panel-h">
        <h2>Datos de la tarjeta</h2>
        <span class="chip chip-verde">Conexión cifrada</span>
      </div>

      <form method="post" id="form-pago" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="token" id="token">
        <input type="hidden" name="payment_method_id" id="paymentMethodId">
        <input type="hidden" name="issuer_id" id="issuerInput">

        <div class="campo">
          <label for="cardNumber">Número de la tarjeta</label>
          <div id="cardNumber" class="mp-campo"></div>
        </div>

        <div class="campo-fila-3">
          <div class="campo">
            <label for="expirationDate">Vencimiento</label>
            <div id="expirationDate" class="mp-campo"></div>
          </div>
          <div class="campo">
            <label for="securityCode">Código de seguridad</label>
            <div id="securityCode" class="mp-campo"></div>
          </div>
          <div class="campo">
            <label for="cardholderName">Titular</label>
            <input type="text" id="cardholderName" autocomplete="off" placeholder="Como aparece en la tarjeta">
          </div>
        </div>

        <div class="campo-fila-3">
          <div class="campo">
            <label for="doc_tipo">Tipo de documento</label>
            <select id="doc_tipo" name="doc_tipo">
              <option value="CC">Cédula de ciudadanía</option>
              <option value="CE">Cédula de extranjería</option>
              <option value="NIT">NIT</option>
              <option value="PAS">Pasaporte</option>
            </select>
          </div>
          <div class="campo">
            <label for="doc_numero">Número de documento</label>
            <input type="text" id="doc_numero" name="doc_numero" inputmode="numeric" required>
          </div>
          <div class="campo">
            <label for="installments">Cuotas</label>
            <select id="installments" name="installments"><option value="1">1 cuota</option></select>
          </div>
        </div>

        <div class="campo">
          <label for="email">Correo para el comprobante</label>
          <input type="email" id="email" name="email" value="<?= h($u['email']) ?>" required>
        </div>

        <div class="campo" id="issuerCampo" hidden>
          <label for="issuer">Banco emisor</label>
          <select id="issuer"></select>
        </div>

        <p class="txt-sm txt-muted" id="mp-error" role="alert"></p>

        <div class="form-acc">
          <button class="btn btn-lg" type="submit" id="btn-pagar" <?= mp_configurado() ? '' : 'disabled' ?>>
            Pagar <?= moneda((float) $f['monto'], $f['moneda']) ?>
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
              <td class="txt-sm txt-muted"><?= h(mp_motivo((string) $i['estado_detalle'])) ?></td>
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
      <div class="panel-h"><h3>Resumen</h3></div>
      <dl class="dl">
        <dt>Factura</dt><dd class="mono"><?= h($f['numero']) ?></dd>
        <dt>Concepto</dt><dd><?= h($f['concepto']) ?></dd>
        <?php if ($f['clave']): ?>
          <dt>Licencia</dt><dd class="mono txt-sm"><?= h($f['clave']) ?></dd>
          <dt>Plan</dt><dd><?= h(ucfirst((string) $f['plan'])) ?> · <?= (int) $f['cupo'] ?> puestos</dd>
        <?php endif; ?>
        <dt>Vence</dt><dd><?= fecha($f['vence_en']) ?></dd>
      </dl>
      <p style="font-size:1.6rem;font-weight:700;color:var(--text-strong);margin:1rem 0 0">
        <?= moneda((float) $f['monto'], $f['moneda']) ?>
      </p>
      <p class="txt-sm txt-muted">Valor final, sin impuestos añadidos.</p>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Seguridad</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li>Los datos de la tarjeta se cifran en tu navegador y viajan directamente a Mercado Pago.</li>
        <li>El portal solo recibe un token de un solo uso: nunca guardamos el número de tu tarjeta.</li>
        <li>Si el pago queda en revisión, te avisamos por correo cuando se acredite.</li>
      </ul>
    </div>
  </aside>
</div>

<style>
  .mp-campo {
    height: 42px; padding: .35rem .8rem; background: var(--bg-elev);
    border: 1px solid var(--border); border-radius: var(--radius);
  }
  .mp-campo.focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(0,120,212,.18); }
</style>

<?php if (mp_configurado()): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
(function () {
  "use strict";
  var mp = new MercadoPago("<?= h(mp_public_key()) ?>");
  var aviso = document.getElementById("mp-error");
  var boton = document.getElementById("btn-pagar");

  var cardForm = mp.cardForm({
    amount: "<?= number_format((float) $f['monto'], 2, '.', '') ?>",
    iframe: true,
    form: {
      id: "form-pago",
      cardNumber:     { id: "cardNumber", placeholder: "0000 0000 0000 0000" },
      expirationDate: { id: "expirationDate", placeholder: "MM/AA" },
      securityCode:   { id: "securityCode", placeholder: "123" },
      cardholderName: { id: "cardholderName" },
      cardholderEmail:{ id: "email" },
      installments:   { id: "installments" },
      identificationType:   { id: "doc_tipo" },
      identificationNumber: { id: "doc_numero" },
      issuer:         { id: "issuer" }
    },
    callbacks: {
      onFormMounted: function (error) {
        if (error) { aviso.textContent = "No se pudo cargar el formulario de pago. Recarga la página."; }
      },
      onSubmit: function (event) {
        event.preventDefault();
        boton.disabled = true;
        boton.textContent = "Procesando…";
        var d = cardForm.getCardFormData();
        if (!d.token) {
          aviso.textContent = "Revisa los datos de la tarjeta.";
          boton.disabled = false;
          boton.textContent = "Pagar";
          return;
        }
        document.getElementById("token").value = d.token;
        document.getElementById("paymentMethodId").value = d.paymentMethodId;
        document.getElementById("issuerInput").value = d.issuerId || "";
        // Envío nativo: el token ya reemplazó a los datos de la tarjeta.
        HTMLFormElement.prototype.submit.call(document.getElementById("form-pago"));
      },
      onFetching: function () {
        aviso.textContent = "Validando la tarjeta…";
        return function () { aviso.textContent = ""; };
      },
      onError: function (errores) {
        boton.disabled = false;
        boton.textContent = "Pagar";
        aviso.textContent = (errores && errores.length)
          ? errores.map(function (e) { return e.message; }).join(" · ")
          : "No se pudo validar la tarjeta.";
      }
    }
  });

  ["cardNumber", "expirationDate", "securityCode"].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener("focusin", function () { el.classList.add("focus"); });
    el.addEventListener("focusout", function () { el.classList.remove("focus"); });
  });
})();
</script>
<?php endif; ?>
<?php pie(); ?>
