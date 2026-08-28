<?php
/**
 * Colegios y sedes que usan el portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');
    $id = post_int('id');

    $datos = [
        'nombre'      => post('nombre'),
        'nit'         => post('nit') ?: null,
        'pais'        => post('pais') ?: 'Colombia',
        'ciudad'      => post('ciudad') ?: null,
        'direccion'   => post('direccion') ?: null,
        'telefono'    => post('telefono') ?: null,
        'email'       => post('email') ?: null,
        'programa_ib' => post('programa_ib') ?: null,
        'estado'      => in_array(post('estado'), ['activo', 'suspendido', 'prueba'], true) ? post('estado') : 'activo',
    ];

    if ($accion === 'crear') {
        if ($datos['nombre'] === '') {
            flash_err('El nombre del colegio es obligatorio.');
        } else {
            $slug = slug($datos['nombre']);
            $base = $slug; $i = 2;
            while (valor('SELECT id FROM colegios WHERE slug = ?', [$slug])) $slug = $base . '-' . $i++;
            $datos['slug'] = $slug;
            $nid = insertar('colegios', $datos);
            auditar('colegio_creado', 'colegios', $nid, $datos['nombre']);
            flash_ok('Colegio registrado.');
        }
    }

    if ($accion === 'editar' && $id) {
        actualizar('colegios', $datos, 'id = :id', ['id' => $id]);
        auditar('colegio_editado', 'colegios', $id);
        flash_ok('Colegio actualizado.');
    }

    if ($accion === 'eliminar' && $id) {
        if ((int) valor('SELECT COUNT(*) FROM usuarios WHERE colegio_id = ?', [$id], 0) > 0) {
            flash_err('No se puede eliminar: todavía tiene usuarios asociados.');
        } else {
            borrar('colegios', 'id = ?', [$id]);
            auditar('colegio_eliminado', 'colegios', $id);
            flash_ok('Colegio eliminado.');
        }
    }
    redirigir('portal/admin/colegios.php');
}

$colegios = filas('SELECT c.*,
                          (SELECT COUNT(*) FROM usuarios u WHERE u.colegio_id = c.id) AS usuarios,
                          (SELECT COUNT(*) FROM usuarios u WHERE u.colegio_id = c.id AND u.rol = "estudiante") AS estudiantes,
                          (SELECT COUNT(*) FROM grupos g WHERE g.colegio_id = c.id AND g.estado = "activo") AS grupos,
                          (SELECT COUNT(*) FROM licencias l WHERE l.colegio_id = c.id AND l.estado = "activa") AS licencias
                     FROM colegios c ORDER BY c.nombre');

$editar = get_int('editar');
$edit = $editar ? fila('SELECT * FROM colegios WHERE id = ?', [$editar]) : null;

cabecera('Colegios', [
    'titulo' => 'Colegios',
    'sub'    => 'Instituciones que trabajan con el portal académico.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Colegios']],
]);
?>
<div class="rejilla rej-lat">
  <div class="panel panel-plano">
    <div class="panel-h"><h2><?= count($colegios) ?> colegio(s)</h2></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Colegio</th><th>Ciudad</th><th class="num">Usuarios</th><th class="num">Grupos</th><th class="num">Licencias</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($colegios as $c): ?>
          <tr>
            <td>
              <strong><?= h($c['nombre']) ?></strong><br>
              <span class="txt-sm txt-muted"><?= h($c['programa_ib'] ?? '') ?><?= $c['nit'] ? ' · NIT ' . h($c['nit']) : '' ?></span>
            </td>
            <td class="txt-sm"><?= h($c['ciudad'] ?? '—') ?><br><span class="txt-muted"><?= h($c['pais']) ?></span></td>
            <td class="num"><?= (int) $c['usuarios'] ?><br><span class="txt-sm txt-muted"><?= (int) $c['estudiantes'] ?> est.</span></td>
            <td class="num"><?= (int) $c['grupos'] ?></td>
            <td class="num"><?= (int) $c['licencias'] ?></td>
            <td><?= etiqueta_estado($c['estado']) ?></td>
            <td class="acc">
              <form method="post" class="btn-fila">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/colegios.php?editar=' . (int) $c['id']) ?>">Editar</a>
                <button class="btn btn-xs btn-err" name="accion" value="eliminar"
                        data-confirmar="¿Eliminar el colegio? Solo es posible si no tiene usuarios.">Eliminar</button>
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
        <h3><?= $edit ? 'Editar colegio' : 'Nuevo colegio' ?></h3>
        <?php if ($edit): ?><a class="txt-sm" href="<?= url('portal/admin/colegios.php') ?>">Cancelar</a><?php endif; ?>
      </div>
      <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" required></div>
      <div class="campo-fila">
        <div class="campo"><label for="nit">NIT o identificación</label><input type="text" id="nit" name="nit" value="<?= h($edit['nit'] ?? '') ?>"></div>
        <div class="campo"><label for="pais">País</label><input type="text" id="pais" name="pais" value="<?= h($edit['pais'] ?? 'Colombia') ?>"></div>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="ciudad">Ciudad</label><input type="text" id="ciudad" name="ciudad" value="<?= h($edit['ciudad'] ?? '') ?>"></div>
        <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" value="<?= h($edit['telefono'] ?? '') ?>"></div>
      </div>
      <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" value="<?= h($edit['direccion'] ?? '') ?>"></div>
      <div class="campo"><label for="email">Correo de contacto</label><input type="email" id="email" name="email" value="<?= h($edit['email'] ?? '') ?>"></div>
      <div class="campo"><label for="programa_ib">Programas IB</label><input type="text" id="programa_ib" name="programa_ib" value="<?= h($edit['programa_ib'] ?? 'PAI y Programa del Diploma') ?>"></div>
      <div class="campo">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
          <?php foreach (['activo' => 'Activo', 'prueba' => 'En prueba', 'suspendido' => 'Suspendido'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($edit['estado'] ?? 'activo') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-block" type="submit"><?= $edit ? 'Guardar cambios' : 'Registrar colegio' ?></button>
    </form>
  </aside>
</div>
<?php pie(); ?>
