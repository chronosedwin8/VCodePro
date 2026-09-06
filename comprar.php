<?php
/**
 * Compra de una licencia desde el sitio público.
 *
 * Crea la cuenta de cliente, la licencia (suspendida hasta el pago) y la
 * factura; después envía al mismo formulario de pago que usa el portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagos_api.php';

$catalogo = plan_catalogo();
$plan = get('plan');
if (!isset($catalogo[$plan])) $plan = plan_sugerido(get_int('licencias') ?: 100);

$d = ['nombre' => '', 'apellidos' => '', 'email' => '', 'telefono' => '', 'colegio' => '', 'nit' => '', 'ciudad' => ''];

if (es_post()) {
    exigir_csrf();
    foreach ($d as $k => $_) $d[$k] = post($k, '');
    $plan = isset($catalogo[post('plan')]) ? post('plan') : $plan;
    $cat  = $catalogo[$plan];

    $error = null;
    if ($d['nombre'] === '' || $d['colegio'] === '') {
        $error = 'Necesitamos tu nombre y el nombre de la institución.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif (empty($_POST['acepto'])) {
        $error = 'Debes aceptar los términos de uso para continuar.';
    }

    if (!$error) {
        $existente = fila('SELECT id, rol FROM usuarios WHERE email = ?', [mb_strtolower($d['email'])]);
        if ($existente && $existente['rol'] !== 'cliente') {
            $error = 'Ese correo ya tiene una cuenta en el portal. Entra con ella para comprar.';
        }
    }

    if ($error) {
        flash_err($error);
    } else {
        $email = mb_strtolower($d['email']);
        $existente = fila('SELECT * FROM usuarios WHERE email = ?', [$email]);

        // 1. Colegio
        $colegioId = (int) (valor('SELECT id FROM colegios WHERE nombre = ?', [$d['colegio']]) ?: 0);
        if (!$colegioId) {
            $slug = slug($d['colegio']); $base = $slug; $i = 2;
            while (valor('SELECT id FROM colegios WHERE slug = ?', [$slug])) $slug = $base . '-' . $i++;
            $colegioId = insertar('colegios', [
                'nombre' => $d['colegio'], 'slug' => $slug,
                'nit' => $d['nit'] ?: null, 'ciudad' => $d['ciudad'] ?: null,
                'email' => $email, 'estado' => 'prueba',
            ]);
        }

        // 2. Cuenta de cliente
        $claveNueva = null;
        if ($existente) {
            $clienteId = (int) $existente['id'];
        } else {
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

        // 3. Licencia, suspendida hasta que el pago se acredite
        $licenciaId = insertar('licencias', [
            'colegio_id' => $colegioId, 'cliente_id' => $clienteId,
            'clave'      => generar_clave_licencia($plan),
            'plan'       => $plan, 'cupo' => $cat['cupo'],
            'emitida_en' => date('Y-m-d'),
            'vence_en'   => date('Y-m-d', strtotime('+' . $cat['meses'] . ' months')),
            'estado'     => 'suspendida',
            'notas'      => 'Compra en línea pendiente de pago.',
        ]);

        // 4. Factura
        $periodo = $cat['meses'] === 1 ? 'un mes' : 'doce meses';
        $facturaId = insertar('facturas', [
            'cliente_id' => $clienteId, 'licencia_id' => $licenciaId,
            'numero'     => nuevo_numero_factura(),
            'concepto'   => 'Licencia ' . $cat['nombre'] . ' · ' . $cat['cupo'] . ' puestos · ' . $periodo,
            'monto'      => $cat['precio'], 'moneda' => 'COP', 'estado' => 'pendiente',
            'emitida_en' => date('Y-m-d'),
            'vence_en'   => date('Y-m-d', strtotime('+15 days')),
        ]);

        foreach (filas('SELECT id FROM usuarios WHERE rol = "admin" AND estado = "activo"') as $a) {
            notificar((int) $a['id'], 'Compra en línea iniciada',
                $d['colegio'] . ' · plan ' . $cat['nombre'], 'portal/admin/facturas.php', 'aviso');
        }
        auditar('compra_iniciada', 'facturas', $facturaId, $d['colegio'] . ' · ' . $plan);

        // 5. Sesión iniciada para que pueda pagar y volver a su panel
        $usuarioFila = fila('SELECT * FROM usuarios WHERE id = ?', [$clienteId]);
        abrir_sesion_usuario($usuarioFila);
        if ($claveNueva) {
            flash_ok('Creamos tu cuenta. Tu contraseña es ' . $claveNueva . ' — cámbiala desde tu perfil.');
        }
        redirigir('portal/cliente/pagar.php?factura=' . $facturaId);
    }
}

$u = usuario();
cabecera('Comprar una licencia', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>Licencia <?= h($catalogo[$plan]['nombre']) ?></h2>
  <p><?= h((string) $catalogo[$plan]['cupo']) ?> puestos ·
     <?= $catalogo[$plan]['meses'] === 1 ? 'un mes' : 'un año' ?> de vigencia.</p>
  <p style="font-size:2rem;color:#fff;font-weight:700;margin:.5rem 0"><?= moneda($catalogo[$plan]['precio']) ?></p>
  <ul class="auth-puntos">
    <li><i>✓</i><span><b>Portal académico incluido</b>84 actividades del ciclo de diseño, de 6.º a 12.º.</span></li>
    <li><i>✓</i><span><b>Acceso inmediato</b>La licencia se activa apenas se acredita el pago.</span></li>
    <li><i>✓</i><span><b>Sin impuestos añadidos</b>Valor final: la licencia se vende como servicio digital internacional.</span></li>
  </ul>
</section>

<section class="auth-panel">
  <div class="auth-caja">
    <h1>Datos de la institución</h1>
    <p>Con ellos emitimos la factura y creamos tu cuenta de cliente.</p>

    <?= pintar_flash() ?>

    <?php if ($u && $u['rol'] === 'cliente'): ?>
      <div class="aviso aviso-info"><div>Ya tienes sesión como <?= h($u['email']) ?>. La compra quedará en esa cuenta.</div></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="plan">Plan</label>
        <select id="plan" name="plan">
          <?php foreach ($catalogo as $k => $c): ?>
            <option value="<?= $k ?>" <?= $k === $plan ? 'selected' : '' ?>>
              <?= h($c['nombre']) ?> · <?= (int) $c['cupo'] ?> puestos · <?= moneda($c['precio']) ?>
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
        <input type="email" id="email" name="email" value="<?= h($u['email'] ?? $d['email']) ?>" required>
      </div>
      <div class="campo">
        <label for="colegio">Institución</label>
        <input type="text" id="colegio" name="colegio" value="<?= h($d['colegio'] ?: ($u['colegio_nombre'] ?? '')) ?>" required>
      </div>
      <div class="campo-fila-3">
        <div class="campo"><label for="nit">NIT</label><input type="text" id="nit" name="nit" value="<?= h($d['nit']) ?>"></div>
        <div class="campo"><label for="ciudad">Ciudad</label><input type="text" id="ciudad" name="ciudad" value="<?= h($d['ciudad']) ?>"></div>
        <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" value="<?= h($d['telefono']) ?>"></div>
      </div>
      <label class="check">
        <input type="checkbox" name="acepto" value="1" required>
        <span>Acepto los <a href="<?= url('privacidad.html') ?>" target="_blank">términos de uso y la política de privacidad</a>.</span>
      </label>
      <button class="btn btn-block btn-lg" type="submit">Continuar al pago</button>
    </form>

    <p class="auth-pie">
      ¿Prefieres una cotización formal u orden de compra?
      <a href="<?= url('contacto.html?motivo=cotizacion') ?>">Escríbenos</a>.
    </p>
  </div>
</section>
<?php pie(['publica' => true]); ?>
