<?php
/**
 * Registro de estudiantes (con código de grupo) y de docentes (aprobación previa).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

iniciar_sesion();
if (usuario()) redirigir(panel_de(rol()));

if (ajuste('registro_abierto', '1') !== '1') {
    cabecera('Registro cerrado', ['publica' => true]);
    echo '<section class="auth-panel"><div class="auth-caja"><h1>Registro cerrado</h1>'
       . '<p>El colegio administra las cuentas del portal. Solicita la tuya a la coordinación de Tecnología.</p>'
       . '<a class="btn" href="' . url('portal/login.php') . '">Volver a entrar</a></div></section>';
    pie(['publica' => true]);
    exit;
}

$d = ['nombre' => '', 'apellidos' => '', 'email' => '', 'rol' => 'estudiante', 'codigo' => ''];

if (es_post()) {
    exigir_csrf();
    foreach ($d as $k => $_) $d[$k] = post($k, $d[$k]);
    $clave  = $_POST['clave'] ?? '';
    $clave2 = $_POST['clave2'] ?? '';
    $rolPedido = in_array($d['rol'], ['estudiante', 'docente'], true) ? $d['rol'] : 'estudiante';

    $grupo = null;
    $error = null;

    if ($clave !== $clave2) {
        $error = 'Las dos contraseñas no coinciden.';
    } elseif ($rolPedido === 'estudiante') {
        $grupo = fila('SELECT * FROM grupos WHERE codigo = ? AND estado = "activo"', [mb_strtoupper($d['codigo'])]);
        if (!$grupo) $error = 'El código de grupo no existe o el grupo ya fue archivado.';
    }

    if (!$error) {
        [$ok, $res] = crear_usuario([
            'nombre'     => $d['nombre'],
            'apellidos'  => $d['apellidos'],
            'email'      => $d['email'],
            'clave'      => $clave,
            'rol'        => $rolPedido,
            'estado'     => $rolPedido === 'docente' ? 'pendiente' : 'activo',
            'colegio_id' => $grupo['colegio_id'] ?? null,
        ]);
        if (!$ok) {
            $error = $res;
        } else {
            $nuevoId = (int) $res;
            if ($grupo) {
                insertar('grupo_estudiantes', ['grupo_id' => $grupo['id'], 'estudiante_id' => $nuevoId, 'estado' => 'activo']);
                // Crea las entregas pendientes de las asignaciones abiertas del grupo.
                foreach (filas('SELECT id FROM asignaciones WHERE grupo_id = ? AND estado = "abierta"', [$grupo['id']]) as $a) {
                    insertar('entregas', ['asignacion_id' => $a['id'], 'estudiante_id' => $nuevoId, 'estado' => 'pendiente']);
                }
                notificar((int) $grupo['docente_id'], 'Nuevo estudiante en ' . $grupo['nombre'],
                    trim($d['nombre'] . ' ' . $d['apellidos']) . ' se inscribió con el código del grupo.',
                    'portal/docente/grupo.php?id=' . $grupo['id']);
                flash_ok('Cuenta creada y matriculada en ' . $grupo['nombre'] . '. Ya puedes entrar.');
            } else {
                foreach (filas('SELECT id FROM usuarios WHERE rol = "admin"') as $ad) {
                    notificar((int) $ad['id'], 'Docente pendiente de aprobación',
                        trim($d['nombre'] . ' ' . $d['apellidos']) . ' solicitó una cuenta de docente.',
                        'portal/admin/usuarios.php?estado=pendiente');
                }
                flash_ok('Solicitud enviada. Un administrador debe aprobar tu cuenta de docente antes del primer ingreso.');
            }
            redirigir('portal/login.php');
        }
    }
    flash_err($error);
}

cabecera('Crear cuenta', ['publica' => true]);
?>
<section class="auth-aside">
  <a class="brand" href="<?= url('index.html') ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="30" height="30">
    <span>vcode<span class="pro">pro</span></span>
  </a>
  <h2>Únete al aula</h2>
  <p>Los estudiantes entran con el código de ocho caracteres que entrega su docente. Los docentes solicitan
     la cuenta y la coordinación la aprueba.</p>
  <ul class="auth-puntos">
    <li><i>✓</i><span><b>Un solo entorno</b>Actividades, bitácora, entregas y calificaciones en el mismo lugar.</span></li>
    <li><i>✓</i><span><b>Evidencia continua</b>Cada fase del ciclo de diseño queda registrada con su fecha.</span></li>
  </ul>
</section>

<section class="auth-panel">
  <div class="auth-caja">
    <div class="auth-tabs">
      <a href="<?= url('portal/login.php') ?>">Entrar</a>
      <a class="on" href="<?= url('portal/registro.php') ?>">Crear cuenta</a>
    </div>

    <h1>Crear cuenta</h1>
    <p>Completa tus datos para acceder al portal académico.</p>

    <?= pintar_flash() ?>

    <form method="post" novalidate>
      <?= csrf_campo() ?>
      <div class="campo">
        <span class="campo-label">Soy</span>
        <label class="check"><input type="radio" name="rol" value="estudiante" <?= $d['rol'] !== 'docente' ? 'checked' : '' ?>><span>Estudiante (necesito el código del grupo)</span></label>
        <label class="check"><input type="radio" name="rol" value="docente" <?= $d['rol'] === 'docente' ? 'checked' : '' ?>><span>Docente (requiere aprobación de la coordinación)</span></label>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="nombre">Nombres</label>
          <input type="text" id="nombre" name="nombre" value="<?= h($d['nombre']) ?>" required>
        </div>
        <div class="campo">
          <label for="apellidos">Apellidos</label>
          <input type="text" id="apellidos" name="apellidos" value="<?= h($d['apellidos']) ?>" required>
        </div>
      </div>
      <div class="campo">
        <label for="email">Correo institucional</label>
        <input type="email" id="email" name="email" value="<?= h($d['email']) ?>" required autocomplete="username">
      </div>
      <div class="campo">
        <label for="codigo">Código del grupo</label>
        <input type="text" id="codigo" name="codigo" value="<?= h($d['codigo']) ?>" maxlength="8" style="text-transform:uppercase" placeholder="Ej.: K7P2M4RD">
        <span class="pista">Solo para estudiantes. Tu docente te lo entrega en la primera clase.</span>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="clave">Contraseña</label>
          <input type="password" id="clave" name="clave" required autocomplete="new-password" data-fuerza="#fuerza">
          <span class="pista" id="fuerza"></span>
        </div>
        <div class="campo">
          <label for="clave2">Repetir contraseña</label>
          <input type="password" id="clave2" name="clave2" required autocomplete="new-password">
        </div>
      </div>
      <button class="btn btn-block btn-lg" type="submit">Crear cuenta</button>
    </form>

    <p class="auth-pie">Al crear la cuenta aceptas la <a href="<?= url('privacidad.html') ?>">política de privacidad</a>.</p>
  </div>
</section>
<?php pie(['publica' => true]); ?>
