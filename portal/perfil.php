<?php
/**
 * Perfil del usuario: datos personales, contraseña y preferencias.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

$u = exigir_login();

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    if ($accion === 'datos') {
        $datos = [
            'nombre'           => post('nombre'),
            'apellidos'        => post('apellidos'),
            'telefono'         => post('telefono') ?: null,
            'documento'        => post('documento') ?: null,
            'cargo'            => post('cargo') ?: null,
            'fecha_nacimiento' => post('fecha_nacimiento') ?: null,
            'tema'             => post('tema') === 'light' ? 'light' : 'dark',
        ];
        if ($datos['nombre'] === '') {
            flash_err('El nombre no puede quedar vacío.');
        } else {
            actualizar('usuarios', $datos, 'id = :id', ['id' => $u['id']]);
            auditar('perfil_actualizado', 'usuarios', $u['id']);
            flash_ok('Perfil actualizado.');
        }
        redirigir('portal/perfil.php');
    }

    if ($accion === 'clave') {
        $actual = $_POST['actual'] ?? '';
        $nueva  = $_POST['nueva'] ?? '';
        $rep    = $_POST['repetir'] ?? '';
        if (!password_verify($actual, $u['password_hash'])) {
            flash_err('La contraseña actual no es correcta.');
        } elseif ($nueva !== $rep) {
            flash_err('Las dos contraseñas nuevas no coinciden.');
        } elseif ($err = validar_clave($nueva)) {
            flash_err($err);
        } else {
            cambiar_clave($u['id'], $nueva);
            flash_ok('Contraseña cambiada. Las demás sesiones se cerraron.');
        }
        redirigir('portal/perfil.php');
    }
}

$insignias = es('estudiante')
    ? filas('SELECT i.* FROM usuario_insignias ui JOIN insignias i ON i.id = ui.insignia_id WHERE ui.usuario_id = ?', [$u['id']])
    : [];
$accesos = filas('SELECT accion, ip, creado_en FROM auditoria WHERE usuario_id = ? ORDER BY id DESC LIMIT 8', [$u['id']]);

cabecera('Mi perfil', [
    'titulo' => 'Mi perfil',
    'sub'    => 'Datos de la cuenta, contraseña y preferencias de visualización.',
    'migas'  => [['Portal', panel_de($u['rol'])], ['Mi perfil']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <form method="post" class="panel" data-avisar>
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="datos">
      <div class="panel-h"><h2>Datos personales</h2></div>
      <div class="campo-fila">
        <div class="campo">
          <label for="nombre">Nombres</label>
          <input type="text" id="nombre" name="nombre" value="<?= h($u['nombre']) ?>" required>
        </div>
        <div class="campo">
          <label for="apellidos">Apellidos</label>
          <input type="text" id="apellidos" name="apellidos" value="<?= h($u['apellidos']) ?>">
        </div>
      </div>
      <div class="campo">
        <label for="email">Correo</label>
        <input type="email" id="email" value="<?= h($u['email']) ?>" disabled>
        <span class="pista">El correo institucional solo lo cambia un administrador.</span>
      </div>
      <div class="campo-fila-3">
        <div class="campo">
          <label for="documento">Documento</label>
          <input type="text" id="documento" name="documento" value="<?= h($u['documento']) ?>">
        </div>
        <div class="campo">
          <label for="telefono">Teléfono</label>
          <input type="tel" id="telefono" name="telefono" value="<?= h($u['telefono']) ?>">
        </div>
        <div class="campo">
          <label for="fecha_nacimiento">Fecha de nacimiento</label>
          <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= h($u['fecha_nacimiento']) ?>">
        </div>
      </div>
      <?php if (es('docente', 'admin', 'cliente')): ?>
      <div class="campo">
        <label for="cargo">Cargo</label>
        <input type="text" id="cargo" name="cargo" value="<?= h($u['cargo']) ?>">
      </div>
      <?php endif; ?>
      <div class="campo">
        <label for="tema">Tema predeterminado</label>
        <select id="tema" name="tema">
          <option value="dark" <?= $u['tema'] === 'dark' ? 'selected' : '' ?>>Oscuro</option>
          <option value="light" <?= $u['tema'] === 'light' ? 'selected' : '' ?>>Claro</option>
        </select>
      </div>
      <div class="form-acc"><button class="btn" type="submit">Guardar cambios</button></div>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="clave">
      <div class="panel-h"><h2>Cambiar contraseña</h2><p>Al cambiarla se cerrarán las demás sesiones.</p></div>
      <div class="campo">
        <label for="actual">Contraseña actual</label>
        <input type="password" id="actual" name="actual" required autocomplete="current-password">
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="nueva">Nueva contraseña</label>
          <input type="password" id="nueva" name="nueva" required autocomplete="new-password" data-fuerza="#fuerza">
          <span class="pista" id="fuerza"></span>
        </div>
        <div class="campo">
          <label for="repetir">Repetir contraseña</label>
          <input type="password" id="repetir" name="repetir" required autocomplete="new-password">
        </div>
      </div>
      <div class="form-acc"><button class="btn" type="submit">Cambiar contraseña</button></div>
    </form>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Cuenta</h3></div>
      <dl class="dl">
        <dt>Rol</dt><dd><?= h(ROLES[$u['rol']] ?? '') ?></dd>
        <dt>Estado</dt><dd><?= etiqueta_estado($u['estado']) ?></dd>
        <dt>Colegio</dt><dd><?= h($u['colegio_nombre'] ?? 'Sin asignar') ?></dd>
        <dt>Último acceso</dt><dd><?= fecha($u['ultimo_acceso'], true) ?></dd>
        <dt>Cuenta creada</dt><dd><?= fecha($u['creado_en']) ?></dd>
      </dl>
    </div>

    <?php if ($insignias): ?>
    <div class="panel">
      <div class="panel-h"><h3>Insignias</h3></div>
      <div class="insignias">
        <?php foreach ($insignias as $i): ?>
          <div class="ins"><div class="cara"><?= h($i['icono']) ?></div><b><?= h($i['nombre']) ?></b><span><?= (int) $i['puntos'] ?> pts</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-h"><h3>Actividad reciente</h3></div>
      <table class="tabla tabla-mini">
        <tbody>
        <?php foreach ($accesos as $a): ?>
          <tr>
            <td><?= h(str_replace('_', ' ', $a['accion'])) ?></td>
            <td class="txt-muted txt-sm"><?= fecha_rel($a['creado_en']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$accesos): ?><tr><td class="txt-muted">Sin registros.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </aside>
</div>
<?php pie(); ?>
