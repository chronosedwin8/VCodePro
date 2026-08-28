<?php
/**
 * Inicio de sesión del portal académico.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

iniciar_sesion();
if (usuario()) redirigir(panel_de(rol()));

$email = '';
if (es_post()) {
    exigir_csrf();
    $email = post('email');
    [$ok, $res] = autenticar($email, $_POST['clave'] ?? '', !empty($_POST['recordar']));
    if ($ok) {
        $destino = $_SESSION['destino'] ?? null;
        unset($_SESSION['destino']);
        flash_ok('Bienvenido, ' . $res['nombre'] . '.');
        redirigir($destino ?: panel_de($res['rol']));
    }
    flash_err($res);
}

cabecera('Iniciar sesión', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>El aula de tecnología, completa y en un solo lugar</h2>
  <p>Setenta actividades del ciclo de diseño, de 6.º a 12.º, con rúbricas del PAI y del Programa del Diploma,
     seguimiento en vivo y evidencias listas para la evaluación.</p>
  <ul class="auth-puntos">
    <li><i>1</i><span><b>Estudiantes</b>Trabajan por fases, registran su bitácora y ven su avance real.</span></li>
    <li><i>2</i><span><b>Docentes</b>Arman grupos, asignan actividades y califican con la rúbrica del criterio.</span></li>
    <li><i>3</i><span><b>Coordinación</b>Informes por nivel, licencias y trazabilidad de todo lo que ocurre.</span></li>
  </ul>
</section>

<section class="auth-panel">
  <div class="auth-caja">
    <div class="auth-tabs">
      <a class="on" href="<?= url('portal/login.php') ?>">Entrar</a>
      <a href="<?= url('portal/registro.php') ?>">Crear cuenta</a>
    </div>

    <h1>Entrar al portal</h1>
    <p>Usa el correo institucional que te entregó el colegio.</p>

    <?= pintar_flash() ?>

    <form method="post" novalidate>
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" value="<?= h($email) ?>" required autocomplete="username" autofocus>
      </div>
      <div class="campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required autocomplete="current-password">
      </div>
      <label class="check">
        <input type="checkbox" name="recordar" value="1">
        <span>Mantener la sesión abierta en este equipo durante 30 días</span>
      </label>
      <button class="btn btn-block btn-lg" type="submit">Entrar</button>
    </form>

    <p class="auth-pie">
      <a href="<?= url('portal/recuperar.php') ?>">Olvidé mi contraseña</a> ·
      <a href="<?= url('index.html') ?>">Volver al sitio</a>
    </p>
  </div>
</section>
<?php pie(['publica' => true]); ?>
