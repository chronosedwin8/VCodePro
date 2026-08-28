<?php
/**
 * Niveles del plan de aula (6.º a 12.º).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $id = post_int('id');
    $datos = [
        'codigo'            => post('codigo'),
        'nombre'            => post('nombre'),
        'grado'             => post('grado'),
        'programa_ib'       => post('programa_ib'),
        'asignatura'        => post('asignatura'),
        'edad'              => post('edad'),
        'descripcion'       => post('descripcion'),
        'contenidos'        => post('contenidos'),
        'proyecto_insignia' => post('proyecto_insignia'),
        'lenguajes'         => post('lenguajes'),
        'color'             => post('color') ?: '#0078d4',
        'orden'             => post_int('orden'),
    ];

    if (post('accion') === 'crear') {
        if ($datos['codigo'] === '' || valor('SELECT id FROM niveles WHERE codigo = ?', [$datos['codigo']])) {
            flash_err('El código del nivel es obligatorio y no puede repetirse.');
        } else {
            $nid = insertar('niveles', $datos);
            auditar('nivel_creado', 'niveles', $nid, $datos['codigo']);
            flash_ok('Nivel creado.');
        }
    } elseif (post('accion') === 'editar' && $id) {
        if (valor('SELECT id FROM niveles WHERE codigo = ? AND id <> ?', [$datos['codigo'], $id])) {
            unset($datos['codigo']);
        }
        actualizar('niveles', $datos, 'id = :id', ['id' => $id]);
        auditar('nivel_editado', 'niveles', $id);
        flash_ok('Nivel actualizado.');
    } elseif (post('accion') === 'eliminar' && $id) {
        if ((int) valor('SELECT COUNT(*) FROM actividades WHERE nivel_id = ?', [$id], 0) > 0) {
            flash_err('No se puede eliminar: el nivel todavía tiene actividades.');
        } else {
            borrar('niveles', 'id = ?', [$id]);
            flash_ok('Nivel eliminado.');
        }
    }
    redirigir('portal/admin/niveles.php');
}

$niveles = filas('SELECT n.*,
                         (SELECT COUNT(*) FROM actividades a WHERE a.nivel_id = n.id) AS actividades,
                         (SELECT COUNT(*) FROM grupos g WHERE g.nivel_id = n.id) AS grupos
                    FROM niveles n ORDER BY n.orden');

$editar = get_int('editar');
$edit = $editar ? fila('SELECT * FROM niveles WHERE id = ?', [$editar]) : null;

cabecera('Niveles IB', [
    'titulo' => 'Niveles del plan de aula',
    'sub'    => 'La progresión de 6.º a 12.º que organiza el banco de actividades.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Niveles IB']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php foreach ($niveles as $n): ?>
      <div class="panel">
        <div class="panel-h">
          <div>
            <h2><?= h($n['grado']) ?> · <?= h($n['nombre']) ?></h2>
            <p><?= h($n['programa_ib']) ?> · <?= h($n['asignatura']) ?> · <?= h($n['edad']) ?></p>
          </div>
          <div class="btn-fila">
            <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/actividades.php?nivel=' . (int) $n['id']) ?>"><?= (int) $n['actividades'] ?> actividades</a>
            <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/niveles.php?editar=' . (int) $n['id']) ?>">Editar</a>
          </div>
        </div>
        <p class="txt-sm"><?= h($n['descripcion']) ?></p>
        <p class="campo-label">Contenidos centrales</p>
        <div class="act-meta">
          <?php foreach (lista($n['contenidos']) as $c): ?><span class="chip chip-gris"><?= h($c) ?></span><?php endforeach; ?>
        </div>
        <dl class="dl mt-2">
          <dt>Proyecto insignia</dt><dd><?= h($n['proyecto_insignia']) ?></dd>
          <dt>Lenguajes</dt><dd><?= h($n['lenguajes']) ?></dd>
          <dt>Grupos activos</dt><dd><?= (int) $n['grupos'] ?></dd>
        </dl>
      </div>
    <?php endforeach; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="<?= $edit ? 'editar' : 'crear' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="panel-h">
        <h3><?= $edit ? 'Editar nivel' : 'Nuevo nivel' ?></h3>
        <?php if ($edit): ?><a class="txt-sm" href="<?= url('portal/admin/niveles.php') ?>">Cancelar</a><?php endif; ?>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="codigo">Código</label><input type="text" id="codigo" name="codigo" value="<?= h($edit['codigo'] ?? '') ?>" required placeholder="N9"></div>
        <div class="campo"><label for="grado">Grado</label><input type="text" id="grado" name="grado" value="<?= h($edit['grado'] ?? '') ?>" required placeholder="9.º"></div>
      </div>
      <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" required></div>
      <div class="campo-fila">
        <div class="campo"><label for="programa_ib">Programa IB</label><input type="text" id="programa_ib" name="programa_ib" value="<?= h($edit['programa_ib'] ?? '') ?>" placeholder="PAI 4"></div>
        <div class="campo"><label for="edad">Edad</label><input type="text" id="edad" name="edad" value="<?= h($edit['edad'] ?? '') ?>" placeholder="14 a 15 años"></div>
      </div>
      <div class="campo"><label for="asignatura">Asignatura</label><input type="text" id="asignatura" name="asignatura" value="<?= h($edit['asignatura'] ?? '') ?>"></div>
      <div class="campo"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" style="min-height:90px"><?= h($edit['descripcion'] ?? '') ?></textarea></div>
      <div class="campo">
        <label for="contenidos">Contenidos centrales</label>
        <textarea id="contenidos" name="contenidos" style="min-height:80px"><?= h($edit['contenidos'] ?? '') ?></textarea>
        <span class="pista">Separa cada contenido con una barra vertical: Listas|Ciclos|Funciones</span>
      </div>
      <div class="campo"><label for="proyecto_insignia">Proyecto insignia</label><input type="text" id="proyecto_insignia" name="proyecto_insignia" value="<?= h($edit['proyecto_insignia'] ?? '') ?>"></div>
      <div class="campo"><label for="lenguajes">Lenguajes</label><input type="text" id="lenguajes" name="lenguajes" value="<?= h($edit['lenguajes'] ?? '') ?>"></div>
      <div class="campo-fila">
        <div class="campo"><label for="color">Color</label><input type="text" id="color" name="color" value="<?= h($edit['color'] ?? '#0078d4') ?>"></div>
        <div class="campo"><label for="orden">Orden</label><input type="number" id="orden" name="orden" value="<?= (int) ($edit['orden'] ?? count($niveles) + 1) ?>" min="0" max="99"></div>
      </div>
      <button class="btn btn-block" type="submit"><?= $edit ? 'Guardar cambios' : 'Crear nivel' ?></button>
      <?php if ($edit): ?>
        <button class="btn btn-err btn-block mt-2" name="accion" value="eliminar"
                data-confirmar="¿Eliminar el nivel? Solo es posible si no tiene actividades.">Eliminar nivel</button>
      <?php endif; ?>
    </form>
  </aside>
</div>
<?php pie(); ?>
