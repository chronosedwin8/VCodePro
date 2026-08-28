<?php
/**
 * Recuperación de contraseña.
 *
 * El portal no envía correo desde el servidor de clase: genera un enlace de un
 * solo uso que la coordinación entrega al usuario. En producción basta con
 * reemplazar la entrega en pantalla por el envío por correo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

iniciar_sesion();
if (usuario()) redirigir(panel_de(rol()));

$token   = get('token');
$enlace  = null;
$usuario = null;

// ------------------------------------------------- restablecer con token --
if ($token !== '') {
    $r = fila('SELECT r.*, u.email FROM recuperaciones r
                 JOIN usuarios u ON u.id = r.usuario_id
                WHERE r.token_hash = ? AND r.usado = 0 AND r.expira_en > NOW()',
              [hash('sha256', $token)]);
    if (!$r) {
        flash_err('El enlace no es válido o ya venció. Solicita uno nuevo.');
        redirigir('portal/recuperar.php');
    }
    $usuario = $r;

    if (es_post()) {
        exigir_csrf();
        $c1 = $_POST['clave'] ?? '';
        $c2 = $_POST['clave2'] ?? '';
        $err = $c1 !== $c2 ? 'Las dos contraseñas no coinciden.' : validar_clave($c1);
        if ($err) {
            flash_err($err);
        } else {
            cambiar_clave((int) $r['usuario_id'], $c1);
            actualizar('recuperaciones', ['usado' => 1], 'id = :id', ['id' => $r['id']]);
            flash_ok('Contraseña actualizada. Ya puedes entrar.');
            redirigir('portal/login.php');
        }
    }
}

// ---------------------------------------------------- solicitar el enlace --
if ($token === '' && es_post()) {
    exigir_csrf();
    $email = mb_strtolower(post('email'));
    $u = fila('SELECT id FROM usuarios WHERE email = ? AND estado <> "suspendido"', [$email]);
    if ($u) {
        borrar('recuperaciones', 'usuario_id = ? AND usado = 0', [$u['id']]);
        $nuevo = bin2hex(random_bytes(32));
        insertar('recuperaciones', [
            'usuario_id' => $u['id'],
            'token_hash' => hash('sha256', $nuevo),
            'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
        ]);
        auditar('recuperacion_solicitada', 'usuarios', (int) $u['id'], $email);
        $enlace = url('portal/recuperar.php?token=' . $nuevo);
    }
    // La respuesta es la misma exista o no la cuenta.
    flash_ok('Si la cuenta existe, el enlace de recuperación ya está disponible. Caduca en una hora.');
}

cabecera('Recuperar contraseña', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>Recupera el acceso</h2>
  <p>El enlace de recuperación es de un solo uso y caduca en una hora. Si no lo recibes,
     la coordinación de Tecnología puede generarlo desde el panel de administración.</p>
</section>

<section class="auth-panel">
  <div class="auth-caja">
    <?php if ($usuario): ?>
      <h1>Nueva contraseña</h1>
      <p>Definirás la contraseña de <strong><?= h($usuario['email']) ?></strong>.</p>
      <?= pintar_flash() ?>
      <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="campo">
          <label for="clave">Nueva contraseña</label>
          <input type="password" id="clave" name="clave" required autocomplete="new-password" data-fuerza="#fuerza" autofocus>
          <span class="pista" id="fuerza"></span>
        </div>
        <div class="campo">
          <label for="clave2">Repetir contraseña</label>
          <input type="password" id="clave2" name="clave2" required autocomplete="new-password">
        </div>
        <button class="btn btn-block btn-lg" type="submit">Guardar contraseña</button>
      </form>
    <?php else: ?>
      <h1>Recuperar contraseña</h1>
      <p>Escribe tu correo institucional y generaremos un enlace de un solo uso.</p>
      <?= pintar_flash() ?>
      <?php if ($enlace): ?>
        <div class="aviso aviso-info">
          <div>
            <strong>Enlace generado</strong><br>
            <a class="mono txt-sm" href="<?= h($enlace) ?>"><?= h($enlace) ?></a>
          </div>
        </div>
      <?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" required autocomplete="username" autofocus>
        </div>
        <button class="btn btn-block btn-lg" type="submit">Generar enlace</button>
      </form>
    <?php endif; ?>
    <p class="auth-pie"><a href="<?= url('portal/login.php') ?>">Volver a entrar</a></p>
  </div>
</section>
<?php pie(['publica' => true]); ?>
