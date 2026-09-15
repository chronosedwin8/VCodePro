<?php
/**
 * Compra de una licencia desde el sitio público.
 *
 * Crea la cuenta de cliente (si no la tiene), el pedido —licencia suspendida
 * hasta el pago y su factura— y envía al formulario de pago del portal.
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

$catalogo = plan_catalogo();
$plan = get('plan');
if (!isset($catalogo[$plan])) $plan = plan_sugerido(get_int('licencias') ?: 100);

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
        $_SESSION['destino'] = 'comprar.php?plan=' . $plan;
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
            if (!$ok) { flash_err((string) $res); redirigir('comprar.php?plan=' . $plan); }
            $clienteId = (int) $res;
        }

        $factura = compra_crear($plan, $clienteId, $colegioId);
        if (!$factura) {
            flash_err('No se pudo registrar el pedido. Inténtalo de nuevo.');
            redirigir('comprar.php?plan=' . $plan);
        }

        avisar_admins('Compra en línea iniciada',
            ($d['colegio'] ?: ($u['colegio_nombre'] ?? '')) . ' · plan ' . $catalogo[$plan]['nombre'] . ' · ' . $factura['numero'],
            'aviso');

        if (!$esCliente) {
            abrir_sesion_usuario(fila('SELECT * FROM usuarios WHERE id = ?', [$clienteId]));
            flash_ok('Creamos tu cuenta. Tu contraseña es ' . $claveNueva . ' — cámbiala desde tu perfil.');
        }
        redirigir('portal/cliente/pagar.php?factura=' . (int) $factura['id']);
    }
}

$sel = $catalogo[$plan];
$actividades = (int) valor('SELECT COUNT(*) FROM actividades WHERE publicada = 1', [], 0);
cabecera('Comprar una licencia', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>Licencia <?= h($sel['nombre']) ?></h2>
  <p><?= h(plan_puestos($sel['cupo'])) ?> · <?= $sel['meses'] === 1 ? 'un mes' : 'un año' ?> de vigencia desde el día del pago.</p>
  <p style="font-size:2rem;color:#fff;font-weight:700;margin:.5rem 0">
    <?= h(plan_pesos($sel['precio'])) ?> <span style="font-size:1rem;font-weight:400;color:#b9c8d9">COP / <?= h(plan_periodo($sel)) ?></span>
  </p>
  <ul class="auth-puntos">
    <li><i>✓</i><span><b>Portal académico incluido</b><?= $actividades ?> actividades del ciclo de diseño, de 6.º a 12.º.</span></li>
    <li><i>✓</i><span><b>Acceso inmediato</b>La licencia se activa apenas Mercado Pago acredita el pago.</span></li>
    <li><i>✓</i><span><b>Pago seguro</b>Pagas en el sitio de Mercado Pago; en el extracto de la tarjeta figura <?= h(MP_DESCRIPTOR) ?>.</span></li>
    <li><i>✓</i><span><b>Sin impuestos añadidos</b>Valor final: la licencia se vende como servicio digital internacional.</span></li>
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

    <form method="post" novalidate>
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="plan">Plan</label>
        <select id="plan" name="plan">
          <?php foreach ($catalogo as $k => $c): ?>
            <option value="<?= h($k) ?>" <?= $k === $plan ? 'selected' : '' ?>>
              <?= h($c['nombre']) ?> · <?= h(plan_puestos($c['cupo'])) ?> · <?= h(plan_pesos($c['precio'])) ?> / <?= h(plan_periodo($c)) ?>
            </option>
          <?php endforeach; ?>
        </select>
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
        <span>Acepto los <a href="<?= url('privacidad.html') ?>" target="_blank">términos de uso y la política de privacidad</a>.</span>
      </label>
      <button class="btn btn-block btn-lg" type="submit" <?= $u && !$esCliente ? 'disabled' : '' ?>>Continuar al pago</button>
    </form>

    <p class="auth-pie">
      ¿Prefieres una cotización formal u orden de compra?
      <a href="<?= url('contacto.html?motivo=cotizacion') ?>">Escríbenos</a>.
    </p>
  </div>
</section>
<?php pie(['publica' => true]); ?>
