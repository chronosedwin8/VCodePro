<?php
/**
 * Compra de una licencia desde el sitio público.
 *
 * Crea la cuenta de cliente (si no la tiene), el pedido —licencia suspendida
 * hasta el pago y su factura— y envía al formulario de pago del portal.
 *
 * Se compra exactamente lo que ofrece la calculadora de precios.html: el plan
 * Personal por cantidad (1 a PLAN_PERSONAL_MAX) y periodo (mensual o anual), y
 * Escuela y Sitio como tarifa única. El precio lo calcula plan_pedido().
 *
 * Dos reglas de seguridad que esta página no puede volver a romper:
 *
 * 1. **Nunca se abre la sesión de una cuenta que ya existe.** Antes bastaba
 *    escribir el correo de un cliente para entrar en su cuenta sin contraseña.
 *    Si el correo ya tiene cuenta, se pide iniciar sesión.
 * 2. **Nunca se asocia al comprador con un colegio existente por su nombre.**
 *    Antes, escribir el nombre de un colegio real vinculaba la cuenta nueva a
 *    ese colegio, y con él a sus licencias y claves. Un comprador anónimo
 *    siempre crea su propia institución; la administración las fusiona si hace
 *    falta.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagos_api.php';

/** Textos del resumen de un pedido, iguales al pintar la página y al cambiar la selección. */
function compra_resumen(array $pd): array {
    $nota = '';
    if ($pd['plan'] === 'personal' && $pd['meses'] === 12) {
        $ahorro = plan_pedido('personal', $pd['cupo'], 'mensual')['monto'] * 12 - $pd['monto'];
        $nota = '12 meses por el precio de ' . PLAN_PERSONAL_MESES_ANUAL . ': ahorras ' . plan_pesos($ahorro) . '.';
    } elseif ($pd['plan'] === 'personal') {
        $nota = 'Sin permanencia: renuevas mes a mes desde tu portal.';
    }
    return [
        'nombre'   => $pd['nombre'],
        'total'    => plan_pesos($pd['monto']),
        'periodo'  => $pd['meses'] === 1 ? 'mes' : 'año',
        'vigencia' => plan_puestos($pd['cupo']) . ' · ' . ($pd['meses'] === 1 ? 'un mes' : 'un año') . ' de vigencia desde el día del pago.',
        'nota'     => $nota,
    ];
}

$catalogo = plan_catalogo();
$plan = get('plan');
if (!isset($catalogo[$plan])) $plan = plan_sugerido(get_int('licencias') ?: 100);
$licencias = max(1, min(PLAN_PERSONAL_MAX, get_int('licencias', 1)));
$periodo = get('periodo') === 'anual' ? 'anual' : 'mensual';

$u = usuario();
$esCliente = $u && $u['rol'] === 'cliente';

$d = ['nombre' => '', 'apellidos' => '', 'email' => '', 'telefono' => '', 'colegio' => '', 'nit' => '', 'ciudad' => ''];
if ($esCliente) {
    $d['nombre']    = (string) $u['nombre'];
    $d['apellidos'] = (string) $u['apellidos'];
    $d['email']     = (string) $u['email'];
    $d['telefono']  = (string) ($u['telefono'] ?? '');
    $d['colegio']   = (string) ($u['colegio_nombre'] ?? '');
}

if (es_post()) {
    exigir_csrf();
    foreach ($d as $k => $_) $d[$k] = post($k, '');
    $plan = isset($catalogo[post('plan')]) ? post('plan') : $plan;
    $licencias = max(1, min(PLAN_PERSONAL_MAX, post_int('licencias', 1)));
    $periodo = post('periodo') === 'anual' ? 'anual' : 'mensual';
    $aqui = 'comprar.php?' . http_build_query(['plan' => $plan, 'licencias' => $licencias, 'periodo' => $periodo]);
    $email = mb_strtolower(trim($d['email']));

    $error = null;
    if ($u && !$esCliente) {
        $error = 'Tu sesión es de ' . mb_strtolower(ROLES[$u['rol']] ?? $u['rol'])
               . '. Las compras se hacen con una cuenta de cliente: cierra la sesión o usa otro navegador.';
    } elseif ($d['nombre'] === '' || ($d['colegio'] === '' && !($esCliente && $u['colegio_id']))) {
        $error = 'Necesitamos tu nombre y el nombre de la institución.';
    } elseif (!$esCliente && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif (empty($_POST['acepto'])) {
        $error = 'Debes aceptar los términos de uso para continuar.';
    }

    if (!$error && !$esCliente && valor('SELECT id FROM usuarios WHERE email = ?', [$email])) {
        // Regla 1: el correo ya tiene cuenta. Se pide la contraseña, no se entra.
        $_SESSION['destino'] = $aqui;
        auditar('compra_requiere_login', 'usuarios', null, $email);
        flash_err('Ese correo ya tiene una cuenta en VCodePro. Entra con tu contraseña y continúa la compra.');
        redirigir('portal/login.php');
    }

    if ($error) {
        flash_err($error);
    } else {
        $claveNueva = null;

        if ($esCliente) {
            // Con sesión de cliente, la compra va a esa cuenta y a su institución.
            $clienteId = (int) $u['id'];
            $colegioId = (int) $u['colegio_id'] ?: null;
        } else {
            // Regla 2: siempre una institución propia, aunque el nombre exista.
            $slug = slug($d['colegio']) ?: 'institucion';
            $base = $slug; $i = 2;
            while (valor('SELECT id FROM colegios WHERE slug = ?', [$slug])) $slug = $base . '-' . $i++;
            $colegioId = insertar('colegios', [
                'nombre' => $d['colegio'], 'slug' => $slug,
                'nit' => $d['nit'] ?: null, 'ciudad' => $d['ciudad'] ?: null,
                'email' => $email, 'estado' => 'prueba',
            ]);

            $claveNueva = 'Vcp' . codigo_aleatorio(6) . random_int(10, 99);
            [$ok, $res] = crear_usuario([
                'nombre' => $d['nombre'], 'apellidos' => $d['apellidos'], 'email' => $email,
                'clave' => $claveNueva, 'rol' => 'cliente', 'estado' => 'activo',
                'colegio_id' => $colegioId, 'telefono' => $d['telefono'] ?: null,
                'cargo' => 'Contacto de licenciamiento',
            ]);
            if (!$ok) { flash_err((string) $res); redirigir($aqui); }
            $clienteId = (int) $res;
        }

        $factura = compra_crear($plan, $clienteId, $colegioId, $licencias, $periodo);
        if (!$factura) {
            flash_err('No se pudo registrar el pedido. Inténtalo de nuevo.');
            redirigir($aqui);
        }

        avisar_admins('Compra en línea iniciada',
            ($d['colegio'] ?: ($u['colegio_nombre'] ?? '')) . ' · ' . $factura['concepto'] . ' · ' . $factura['numero'],
            'aviso');

        if (!$esCliente) {
            abrir_sesion_usuario(fila('SELECT * FROM usuarios WHERE id = ?', [$clienteId]));
            flash_ok('Creamos tu cuenta. Tu contraseña es ' . $claveNueva . ' — cámbiala desde tu perfil.');
        }
        redirigir('portal/cliente/pagar.php?factura=' . (int) $factura['id']);
    }
}

$pedido = plan_pedido($plan, $licencias, $periodo);
$resumen = compra_resumen($pedido);

// Todas las combinaciones posibles, calculadas en el servidor: al cambiar la
// selección, el navegador solo muestra la que corresponde, sin repetir precios.
$opciones = [];
foreach (array_keys($catalogo) as $k) {
    if ($k === 'personal') {
        for ($n = 1; $n <= PLAN_PERSONAL_MAX; $n++) {
            foreach (['mensual', 'anual'] as $per) {
                $opciones["personal|$n|$per"] = compra_resumen(plan_pedido('personal', $n, $per));
            }
        }
    } else {
        $opciones[$k] = compra_resumen(plan_pedido($k));
    }
}

$actividades = (int) valor('SELECT COUNT(*) FROM actividades WHERE publicada = 1', [], 0);
cabecera('Comprar una licencia', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>Licencia <span id="compra-nombre"><?= h($resumen['nombre']) ?></span></h2>
  <p id="compra-vigencia"><?= h($resumen['vigencia']) ?></p>
  <p style="font-size:2rem;color:#fff;font-weight:700;margin:.5rem 0">
    <span id="compra-total"><?= h($resumen['total']) ?></span> <span style="font-size:1rem;font-weight:400;color:#b9c8d9">COP / <span id="compra-periodo"><?= h($resumen['periodo']) ?></span></span>
  </p>
  <p id="compra-nota" style="color:#b9c8d9" <?= $resumen['nota'] === '' ? 'hidden' : '' ?>><?= h($resumen['nota']) ?></p>
  <ul class="auth-puntos">
    <li><i>✓</i><span><b>Portal académico incluido</b><?= $actividades ?> actividades del ciclo de diseño, de 6.º a 12.º.</span></li>
    <li><i>✓</i><span><b>Acceso inmediato</b>La licencia se activa apenas Mercado Pago acredita el pago.</span></li>
    <li><i>✓</i><span><b>Pago seguro</b>Pagas en el sitio de Mercado Pago; en el extracto de la tarjeta figura <?= h(MP_DESCRIPTOR) ?>.</span></li>
    <li><i>✓</i><span><b>Impuestos claros</b>Precio en pesos colombianos; lo que corresponda se muestra antes de confirmar el pago.</span></li>
  </ul>
</section>

<section class="auth-panel">
  <div class="auth-caja">
    <h1>Datos de la institución</h1>
    <p>Con ellos emitimos la factura<?= $esCliente ? '' : ' y creamos tu cuenta de cliente' ?>.</p>

    <?= pintar_flash() ?>

    <?php if ($esCliente): ?>
      <div class="aviso aviso-info"><div>Compras con tu cuenta <strong><?= h($u['email']) ?></strong>. La licencia quedará en ella.</div></div>
    <?php elseif ($u): ?>
      <div class="aviso aviso-warn"><div>Tienes sesión como <?= h(mb_strtolower(ROLES[$u['rol']] ?? $u['rol'])) ?>. Para comprar necesitas una cuenta de cliente.</div></div>
    <?php endif; ?>

    <form method="post" novalidate id="form-compra">
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="plan">Plan</label>
        <select id="plan" name="plan">
          <?php foreach ($catalogo as $k => $c): ?>
            <option value="<?= h($k) ?>" <?= $k === $plan ? 'selected' : '' ?>>
              <?php if ($k === 'personal'): ?>
                <?= h($c['nombre']) ?> · por licencia · <?= h(plan_pesos($c['precio'])) ?> / mes
              <?php else: ?>
                <?= h($c['nombre']) ?> · <?= h(plan_puestos($c['cupo'])) ?> · <?= h(plan_pesos($c['precio'])) ?> / <?= h(plan_periodo($c)) ?>
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo-fila" id="campos-personal" <?= $plan === 'personal' ? '' : 'hidden' ?>>
        <div class="campo">
          <label for="licencias">Licencias</label>
          <select id="licencias" name="licencias">
            <?php for ($n = 1; $n <= PLAN_PERSONAL_MAX; $n++): ?>
              <option value="<?= $n ?>" <?= $n === $licencias ? 'selected' : '' ?>><?= $n ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="campo">
          <label for="periodo">Pago</label>
          <select id="periodo" name="periodo">
            <option value="mensual" <?= $periodo === 'mensual' ? 'selected' : '' ?>>Cada mes</option>
            <option value="anual" <?= $periodo === 'anual' ? 'selected' : '' ?>>Anual · 12 meses por <?= PLAN_PERSONAL_MESES_ANUAL ?></option>
          </select>
        </div>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="nombre">Nombres</label><input type="text" id="nombre" name="nombre" value="<?= h($d['nombre']) ?>" required></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="<?= h($d['apellidos']) ?>"></div>
      </div>
      <div class="campo">
        <label for="email">Correo institucional</label>
        <input type="email" id="email" name="email" value="<?= h($d['email']) ?>" required <?= $esCliente ? 'readonly' : '' ?>>
        <?php if (!$esCliente): ?>
          <span class="pista">¿Ya tienes cuenta? <a href="<?= url('portal/login.php') ?>">Entra primero</a> y la compra quedará en ella.</span>
        <?php endif; ?>
      </div>
      <div class="campo">
        <label for="colegio">Institución</label>
        <input type="text" id="colegio" name="colegio" value="<?= h($d['colegio']) ?>" <?= $esCliente && $u['colegio_id'] ? 'readonly' : 'required' ?>>
      </div>
      <?php if (!$esCliente): ?>
      <div class="campo-fila-3">
        <div class="campo"><label for="nit">NIT</label><input type="text" id="nit" name="nit" value="<?= h($d['nit']) ?>"></div>
        <div class="campo"><label for="ciudad">Ciudad</label><input type="text" id="ciudad" name="ciudad" value="<?= h($d['ciudad']) ?>"></div>
        <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" value="<?= h($d['telefono']) ?>"></div>
      </div>
      <?php endif; ?>
      <label class="check">
        <input type="checkbox" name="acepto" value="1" required>
        <span>Acepto los <a href="<?= url('terminos.html') ?>" target="_blank">términos de servicio</a>, el <a href="<?= url('privacidad.html') ?>" target="_blank">aviso de privacidad</a> y la <a href="<?= url('reembolsos.html') ?>" target="_blank">política de reembolso</a>.</span>
      </label>
      <button class="btn btn-block btn-lg" type="submit" <?= $u && !$esCliente ? 'disabled' : '' ?>>Continuar al pago · <span id="compra-boton"><?= h($resumen['total']) ?></span></button>
    </form>

    <p class="auth-pie">
      ¿Prefieres una cotización formal u orden de compra?
      <a href="<?= url('contacto.html?motivo=cotizacion') ?>">Escríbenos</a>.
    </p>
  </div>
</section>
<script>
(function () {
  var opciones = <?= json_encode($opciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var plan = document.getElementById("plan");
  var licencias = document.getElementById("licencias");
  var periodo = document.getElementById("periodo");
  var personal = document.getElementById("campos-personal");
  var poner = function (id, texto) { var el = document.getElementById(id); if (el) { el.textContent = texto; } };

  function actualizar() {
    var esPersonal = plan.value === "personal";
    personal.hidden = !esPersonal;
    var r = opciones[esPersonal ? "personal|" + licencias.value + "|" + periodo.value : plan.value];
    if (!r) { return; }
    poner("compra-nombre", r.nombre);
    poner("compra-vigencia", r.vigencia);
    poner("compra-total", r.total);
    poner("compra-periodo", r.periodo);
    poner("compra-boton", r.total);
    var nota = document.getElementById("compra-nota");
    nota.textContent = r.nota;
    nota.hidden = r.nota === "";
  }
  [plan, licencias, periodo].forEach(function (el) { el.addEventListener("change", actualizar); });
})();
</script>
<?php pie(['publica' => true]); ?>
