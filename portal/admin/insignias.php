<?php
/**
 * Insignias del portal y su concesión.
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
        'codigo'      => slug(post('codigo') ?: post('nombre')),
        'nombre'      => post('nombre'),
        'descripcion' => post('descripcion'),
        'icono'       => mb_substr(post('icono') ?: '*', 0, 8),
        'puntos'      => max(0, post_int('puntos')),
    ];

    if ($accion === 'crear') {
        if ($datos['nombre'] === '' || valor('SELECT id FROM insignias WHERE codigo = ?', [$datos['codigo']])) {
            flash_err('El nombre es obligatorio y el código no puede repetirse.');
        } else {
            insertar('insignias', $datos);
            flash_ok('Insignia creada.');
        }
    } elseif ($accion === 'editar' && $id) {
        if (valor('SELECT id FROM insignias WHERE codigo = ? AND id <> ?', [$datos['codigo'], $id])) unset($datos['codigo']);
        actualizar('insignias', $datos, 'id = :id', ['id' => $id]);
        flash_ok('Insignia actualizada.');
    } elseif ($accion === 'eliminar' && $id) {
        borrar('insignias', 'id = ?', [$id]);
        flash_ok('Insignia eliminada.');
    } elseif ($accion === 'otorgar') {
        $est = fila('SELECT id FROM usuarios WHERE email = ? AND rol = "estudiante"', [mb_strtolower(post('email'))]);
        $ins = fila('SELECT codigo FROM insignias WHERE id = ?', [post_int('insignia_id')]);
        if (!$est || !$ins) {
            flash_err('No encontramos ese estudiante o esa insignia.');
        } elseif (otorgar_insignia((int) $est['id'], $ins['codigo'])) {
            auditar('insignia_otorgada', 'usuarios', (int) $est['id'], $ins['codigo']);
            flash_ok('Insignia concedida.');
        } else {
            flash_err('El estudiante ya tenía esa insignia.');
        }
    }
    redirigir('portal/admin/insignias.php');
}

$insignias = filas('SELECT i.*, (SELECT COUNT(*) FROM usuario_insignias ui WHERE ui.insignia_id = i.id) AS otorgadas
                      FROM insignias i ORDER BY i.puntos');

$ranking = filas('SELECT CONCAT(u.nombre, " ", u.apellidos) AS estudiante, u.email,
                         COUNT(ui.id) AS insignias, COALESCE(SUM(i.puntos),0) AS puntos
                    FROM usuario_insignias ui
                    JOIN usuarios u ON u.id = ui.usuario_id
                    JOIN insignias i ON i.id = ui.insignia_id
                GROUP BY u.id, u.nombre, u.apellidos, u.email
                ORDER BY puntos DESC LIMIT 15');

$editar = get_int('editar');
$edit = $editar ? fila('SELECT * FROM insignias WHERE id = ?', [$editar]) : null;

cabecera('Insignias', [
    'titulo' => 'Insignias',
    'sub'    => 'Reconocimientos del portal y su concesión manual.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Insignias']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h"><h2><?= count($insignias) ?> insignia(s)</h2></div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Insignia</th><th>Requisito</th><th class="num">Puntos</th><th class="num">Otorgadas</th><th class="acc">&nbsp;</th></tr></thead>
          <tbody>
          <?php foreach ($insignias as $i): ?>
            <tr>
              <td><strong><?= h($i['icono']) ?> <?= h($i['nombre']) ?></strong><br><span class="act-cod"><?= h($i['codigo']) ?></span></td>
              <td class="txt-sm txt-muted"><?= h($i['descripcion']) ?></td>
              <td class="num"><?= (int) $i['puntos'] ?></td>
              <td class="num"><?= (int) $i['otorgadas'] ?></td>
              <td class="acc">
                <form method="post" class="btn-fila">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                  <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/insignias.php?editar=' . (int) $i['id']) ?>">Editar</a>
                  <button class="btn btn-xs btn-err" name="accion" value="eliminar" data-confirmar="¿Eliminar la insignia y sus concesiones?">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel panel-plano">
      <div class="panel-h"><h2>Estudiantes destacados</h2><p>Por puntos acumulados</p></div>
      <?php if (!$ranking): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Todavía no se ha otorgado ninguna insignia.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th class="num">#</th><th>Estudiante</th><th class="num">Insignias</th><th class="num">Puntos</th></tr></thead>
            <tbody>
            <?php foreach ($ranking as $k => $r): ?>
              <tr>
                <td class="num"><?= $k + 1 ?></td>
                <td><strong><?= h($r['estudiante']) ?></strong><br><span class="txt-sm txt-muted"><?= h($r['email']) ?></span></td>
                <td class="num"><?= (int) $r['insignias'] ?></td>
                <td class="num"><?= (int) $r['puntos'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="<?= $edit ? 'editar' : 'crear' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="panel-h">
        <h3><?= $edit ? 'Editar insignia' : 'Nueva insignia' ?></h3>
        <?php if ($edit): ?><a class="txt-sm" href="<?= url('portal/admin/insignias.php') ?>">Cancelar</a><?php endif; ?>
      </div>
      <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" required></div>
      <div class="campo"><label for="codigo">Código</label><input type="text" id="codigo" name="codigo" value="<?= h($edit['codigo'] ?? '') ?>" placeholder="Se genera del nombre"></div>
      <div class="campo"><label for="descripcion">Requisito</label><textarea id="descripcion" name="descripcion" style="min-height:80px"><?= h($edit['descripcion'] ?? '') ?></textarea></div>
      <div class="campo-fila">
        <div class="campo"><label for="icono">Icono</label><input type="text" id="icono" name="icono" value="<?= h($edit['icono'] ?? '*') ?>" maxlength="8"></div>
        <div class="campo"><label for="puntos">Puntos</label><input type="number" id="puntos" name="puntos" value="<?= (int) ($edit['puntos'] ?? 10) ?>" min="0" max="500"></div>
      </div>
      <button class="btn btn-block" type="submit"><?= $edit ? 'Guardar' : 'Crear insignia' ?></button>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="otorgar">
      <div class="panel-h"><h3>Otorgar manualmente</h3></div>
      <div class="campo"><label for="email">Correo del estudiante</label><input type="email" id="email" name="email" required></div>
      <div class="campo">
        <label for="insignia_id">Insignia</label>
        <select id="insignia_id" name="insignia_id">
          <?php foreach ($insignias as $i): ?><option value="<?= (int) $i['id'] ?>"><?= h($i['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-block btn-ghost" type="submit">Otorgar</button>
    </form>
  </aside>
</div>
<?php pie(); ?>
