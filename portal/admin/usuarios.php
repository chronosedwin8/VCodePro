<?php
/**
 * Administración de usuarios: alta, edición, estado y contraseñas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/ia.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');
    $id     = post_int('id');

    if ($accion === 'crear') {
        [$ok, $res] = crear_usuario([
            'nombre'     => post('nombre'),
            'apellidos'  => post('apellidos'),
            'email'      => post('email'),
            'clave'      => post('clave') ?: ('Vcp' . codigo_aleatorio(6) . random_int(10, 99)),
            'rol'        => post('rol'),
            'estado'     => post('estado') ?: 'activo',
            'colegio_id' => post_int('colegio_id') ?: null,
            'cargo'      => post('cargo') ?: null,
            'documento'  => post('documento') ?: null,
            'telefono'   => post('telefono') ?: null,
        ]);
        flash($ok ? 'ok' : 'err', $ok ? 'Usuario creado correctamente.' : $res);
    }

    if ($accion === 'editar' && $id) {
        $datos = [
            'nombre'     => post('nombre'),
            'apellidos'  => post('apellidos'),
            'rol'        => in_array(post('rol'), array_keys(ROLES), true) ? post('rol') : 'estudiante',
            'estado'     => in_array(post('estado'), ['activo', 'pendiente', 'suspendido'], true) ? post('estado') : 'activo',
            'colegio_id' => post_int('colegio_id') ?: null,
            'cargo'      => post('cargo') ?: null,
            'documento'  => post('documento') ?: null,
            'telefono'   => post('telefono') ?: null,
        ];
        $email = mb_strtolower(post('email'));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            && !valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $id])) {
            $datos['email'] = $email;
        }
        actualizar('usuarios', $datos, 'id = :id', ['id' => $id]);
        auditar('usuario_editado', 'usuarios', $id);
        flash_ok('Usuario actualizado.');
    }

    if ($accion === 'estado' && $id) {
        actualizar('usuarios', ['estado' => post('valor')], 'id = :id', ['id' => $id]);
        auditar('usuario_estado', 'usuarios', $id, post('valor'));
        flash_ok('Estado actualizado.');
    }

    // El asistente de IA se concede docente por docente y se puede retirar.
    if ($accion === 'ia' && $id) {
        $x = fila('SELECT rol, ia_habilitada, email FROM usuarios WHERE id = ?', [$id]);
        if ($x && $x['rol'] === 'docente') {
            $nuevo = empty($x['ia_habilitada']) ? 1 : 0;
            actualizar('usuarios', ['ia_habilitada' => $nuevo], 'id = :id', ['id' => $id]);
            auditar('usuario_ia', 'usuarios', $id, ($nuevo ? 'habilitado' : 'retirado') . ' · ' . $x['email']);
            flash_ok($nuevo ? 'Asistente de IA habilitado para ' . $x['email'] . '.'
                            : 'Asistente de IA retirado a ' . $x['email'] . '.');
        }
        redirigir('portal/admin/usuarios.php');
    }

    if ($accion === 'clave' && $id) {
        $nueva = 'Vcp' . codigo_aleatorio(6) . random_int(10, 99);
        cambiar_clave($id, $nueva);
        flash_ok('Contraseña temporal: ' . $nueva);
    }

    if ($accion === 'eliminar' && $id) {
        if ($id === $u['id']) {
            flash_err('No puedes eliminar tu propia cuenta.');
        } elseif ($id === (int) valor('SELECT MIN(id) FROM usuarios WHERE rol = "admin"')) {
            flash_err('No se puede eliminar la cuenta de administración principal.');
        } else {
            borrar('usuarios', 'id = ?', [$id]);
            auditar('usuario_eliminado', 'usuarios', $id);
            flash_ok('Usuario eliminado junto con su trabajo.');
        }
    }
    redirigir('portal/admin/usuarios.php' . (get('estado') ? '?estado=' . get('estado') : ''));
}

// ------------------------------------------------------------- listado ----
$rolF    = get('rol');
$estadoF = get('estado');
$buscar  = get('q');
$editar  = get_int('editar');

$where = ['1=1']; $params = [];
if (in_array($rolF, array_keys(ROLES), true)) { $where[] = 'u.rol = ?'; $params[] = $rolF; }
if (in_array($estadoF, ['activo', 'pendiente', 'suspendido'], true)) { $where[] = 'u.estado = ?'; $params[] = $estadoF; }
if ($buscar !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ? OR u.documento LIKE ?)';
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%", "%$buscar%");
}

$usuarios = filas('SELECT u.*, c.nombre AS colegio,
                          (SELECT COUNT(*) FROM grupo_estudiantes ge WHERE ge.estudiante_id = u.id) AS grupos_est,
                          (SELECT COUNT(*) FROM grupos g WHERE g.docente_id = u.id) AS grupos_doc
                     FROM usuarios u LEFT JOIN colegios c ON c.id = u.colegio_id
                    WHERE ' . implode(' AND ', $where) . '
                 ORDER BY u.rol, u.apellidos, u.nombre LIMIT 500', $params);

$colegios = filas('SELECT id, nombre FROM colegios ORDER BY nombre');
$edit = $editar ? fila('SELECT * FROM usuarios WHERE id = ?', [$editar]) : null;

if (get('exportar') === 'csv') {
    descargar_csv('usuarios', ['Nombres', 'Apellidos', 'Correo', 'Rol', 'Estado', 'Colegio', 'Último acceso'],
        array_map(fn($x) => [$x['nombre'], $x['apellidos'], $x['email'], ROLES[$x['rol']], $x['estado'], $x['colegio'] ?? '', $x['ultimo_acceso'] ?? ''], $usuarios));
}

cabecera('Usuarios', [
    'titulo' => 'Usuarios',
    'sub'    => count($usuarios) . ' cuenta(s) con el filtro actual.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Usuarios']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/usuarios.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<form class="acciones-barra" method="get">
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar por nombre, correo o documento" class="crece">
  <select name="rol">
    <option value="">Todos los roles</option>
    <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $rolF === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select>
  <select name="estado">
    <option value="">Todos los estados</option>
    <?php foreach (['activo' => 'Activos', 'pendiente' => 'Pendientes', 'suspendido' => 'Suspendidos'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/admin/usuarios.php') ?>">Limpiar</a>
</form>

<div class="rejilla rej-lat">
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Usuario</th><th>Rol</th><th>Colegio</th><th>Estado</th><th>Último acceso</th><th class="acc">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $x): ?>
          <tr>
            <td>
              <strong><?= h(trim($x['apellidos'] . ', ' . $x['nombre'])) ?></strong><br>
              <span class="txt-sm txt-muted"><?= h($x['email']) ?></span>
              <?php if ($x['rol'] === 'estudiante' && (int) $x['grupos_est']): ?>
                <span class="chip chip-gris"><?= (int) $x['grupos_est'] ?> grupo(s)</span>
              <?php elseif ($x['rol'] === 'docente' && (int) $x['grupos_doc']): ?>
                <span class="chip chip-gris"><?= (int) $x['grupos_doc'] ?> grupo(s)</span>
              <?php endif; ?>
              <?php if ($x['rol'] === 'docente' && !empty($x['ia_habilitada'])): ?>
                <span class="chip chip-azul">IA</span>
              <?php endif; ?>
            </td>
            <td><?= h(ROLES[$x['rol']]) ?></td>
            <td class="txt-sm txt-muted"><?= h($x['colegio'] ?? '—') ?></td>
            <td><?= etiqueta_estado($x['estado']) ?></td>
            <td class="txt-sm txt-muted"><?= $x['ultimo_acceso'] ? fecha_rel($x['ultimo_acceso']) : 'nunca' ?></td>
            <td class="acc">
              <form method="post" class="btn-fila">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/usuarios.php?editar=' . (int) $x['id']) ?>">Editar</a>
                <?php if ($x['estado'] === 'pendiente'): ?>
                  <button class="btn btn-xs btn-ok" name="accion" value="estado" onclick="this.form.valor.value='activo'">Aprobar</button>
                <?php elseif ($x['estado'] === 'activo'): ?>
                  <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='suspendido'"
                          data-confirmar="¿Suspender esta cuenta?">Suspender</button>
                <?php else: ?>
                  <button class="btn btn-xs btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='activo'">Reactivar</button>
                <?php endif; ?>
                <?php if ($x['rol'] === 'docente'): ?>
                  <button class="btn btn-xs <?= empty($x['ia_habilitada']) ? 'btn-ghost' : 'btn-ok' ?>" name="accion" value="ia"
                          data-confirmar="<?= empty($x['ia_habilitada']) ? 'Este docente podrá calificar con IA y redactar actividades con ella. ¿Habilitar?' : '¿Retirar el asistente de IA a este docente?' ?>">
                    <?= empty($x['ia_habilitada']) ? 'Dar IA' : 'Quitar IA' ?>
                  </button>
                <?php endif; ?>
                <button class="btn btn-xs btn-ghost" name="accion" value="clave" data-confirmar="¿Generar una contraseña temporal?">Clave</button>
                <button class="btn btn-xs btn-err" name="accion" value="eliminar"
                        data-confirmar="Se eliminará la cuenta y todo su trabajo. Esta acción no se puede deshacer. ¿Continuar?">Eliminar</button>
                <input type="hidden" name="valor" value="activo">
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="<?= $edit ? 'editar' : 'crear' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="panel-h">
        <h3><?= $edit ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
        <?php if ($edit): ?><a class="txt-sm" href="<?= url('portal/admin/usuarios.php') ?>">Cancelar</a><?php endif; ?>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="nombre">Nombres</label><input type="text" id="nombre" name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" required></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="<?= h($edit['apellidos'] ?? '') ?>"></div>
      </div>
      <div class="campo">
        <label for="email">Correo</label>
        <input type="email" id="email" name="email" value="<?= h($edit['email'] ?? '') ?>" required>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="rol">Rol</label>
          <select id="rol" name="rol">
            <?php foreach (ROLES as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($edit['rol'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <?php foreach (['activo' => 'Activo', 'pendiente' => 'Pendiente', 'suspendido' => 'Suspendido'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($edit['estado'] ?? 'activo') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="campo">
        <label for="colegio_id">Colegio</label>
        <select id="colegio_id" name="colegio_id">
          <option value="0">Sin asignar</option>
          <?php foreach ($colegios as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($edit['colegio_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="documento">Documento</label><input type="text" id="documento" name="documento" value="<?= h($edit['documento'] ?? '') ?>"></div>
        <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" value="<?= h($edit['telefono'] ?? '') ?>"></div>
      </div>
      <div class="campo">
        <label for="cargo">Cargo</label>
        <input type="text" id="cargo" name="cargo" value="<?= h($edit['cargo'] ?? '') ?>">
      </div>
      <?php if (!$edit): ?>
        <div class="campo">
          <label for="clave">Contraseña inicial</label>
          <input type="text" id="clave" name="clave" placeholder="Se genera si la dejas vacía">
        </div>
      <?php endif; ?>
      <button class="btn btn-block" type="submit"><?= $edit ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </form>
  </aside>
</div>
<?php pie(); ?>
