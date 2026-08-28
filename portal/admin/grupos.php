<?php
/**
 * Grupos de todo el colegio, con reasignación de docente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

if (es_post()) {
    exigir_csrf();
    $id = post_int('id');
    $accion = post('accion');

    if ($accion === 'reasignar' && $id) {
        $doc = post_int('docente_id');
        if (valor('SELECT id FROM usuarios WHERE id = ? AND rol = "docente"', [$doc])) {
            actualizar('grupos', ['docente_id' => $doc], 'id = :id', ['id' => $id]);
            actualizar('asignaciones', ['docente_id' => $doc], 'grupo_id = :g', ['g' => $id]);
            auditar('grupo_reasignado', 'grupos', $id, 'docente ' . $doc);
            flash_ok('Grupo reasignado al nuevo docente.');
        } else {
            flash_err('El usuario seleccionado no es docente.');
        }
    }

    if ($accion === 'estado' && $id) {
        actualizar('grupos', ['estado' => post('valor') === 'archivado' ? 'archivado' : 'activo'], 'id = :id', ['id' => $id]);
        flash_ok('Estado del grupo actualizado.');
    }

    if ($accion === 'eliminar' && $id) {
        borrar('grupos', 'id = ?', [$id]);
        auditar('grupo_eliminado', 'grupos', $id);
        flash_ok('Grupo eliminado con sus asignaciones y entregas.');
    }
    redirigir('portal/admin/grupos.php');
}

$nivelF = get_int('nivel');
$where = ['1=1']; $params = [];
if ($nivelF) { $where[] = 'g.nivel_id = ?'; $params[] = $nivelF; }

$grupos = filas('SELECT g.*, n.grado, n.nombre AS nivel, c.nombre AS colegio,
                        CONCAT(d.nombre, " ", d.apellidos) AS docente,
                        (SELECT COUNT(*) FROM grupo_estudiantes ge WHERE ge.grupo_id = g.id AND ge.estado = "activo") AS estudiantes,
                        (SELECT COUNT(*) FROM asignaciones a WHERE a.grupo_id = g.id) AS asignaciones,
                        (SELECT ROUND(AVG(e.progreso)) FROM entregas e
                           JOIN asignaciones a ON a.id = e.asignacion_id WHERE a.grupo_id = g.id) AS avance
                   FROM grupos g
                   JOIN niveles n ON n.id = g.nivel_id
                   JOIN usuarios d ON d.id = g.docente_id
              LEFT JOIN colegios c ON c.id = g.colegio_id
                  WHERE ' . implode(' AND ', $where) . '
               ORDER BY g.estado, n.orden, g.nombre', $params);

$docentes = filas('SELECT id, nombre, apellidos FROM usuarios WHERE rol = "docente" AND estado = "activo" ORDER BY apellidos');
$niveles  = filas('SELECT id, grado, nombre FROM niveles ORDER BY orden');

cabecera('Grupos', [
    'titulo' => 'Grupos del colegio',
    'sub'    => count($grupos) . ' grupo(s) registrados.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Grupos']],
]);
?>
<form class="acciones-barra" method="get">
  <select name="nivel" onchange="this.form.submit()">
    <option value="0">Todos los niveles</option>
    <?php foreach ($niveles as $n): ?>
      <option value="<?= (int) $n['id'] ?>" <?= $nivelF === (int) $n['id'] ? 'selected' : '' ?>><?= h($n['grado'] . ' · ' . $n['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="search" data-filtra="#tabla-grupos" placeholder="Buscar grupo, docente o código" class="crece">
</form>

<div class="panel panel-plano">
  <div class="tabla-caja">
    <table class="tabla" id="tabla-grupos">
      <thead><tr><th>Grupo</th><th>Docente</th><th>Código</th><th class="num">Estudiantes</th><th style="min-width:120px">Avance</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($grupos as $g): ?>
        <tr>
          <td>
            <strong><a href="<?= url('portal/docente/grupo.php?id=' . (int) $g['id']) ?>"><?= h($g['nombre']) ?></a></strong><br>
            <span class="txt-sm txt-muted"><?= h($g['grado']) ?> · <?= h($g['colegio'] ?? 'sin colegio') ?> · <?= (int) $g['anio'] ?></span>
          </td>
          <td class="txt-sm"><?= h($g['docente']) ?></td>
          <td><code class="mono copiar" data-copiar="<?= h($g['codigo']) ?>"><?= h($g['codigo']) ?></code></td>
          <td class="num"><?= (int) $g['estudiantes'] ?><br><span class="txt-sm txt-muted"><?= (int) $g['asignaciones'] ?> act.</span></td>
          <td><?= barra((int) ($g['avance'] ?? 0)) ?></td>
          <td><?= etiqueta_estado($g['estado']) ?><?= $g['modo_examen'] ? '<br><span class="chip chip-ambar">Examen</span>' : '' ?></td>
          <td class="acc">
            <form method="post" class="btn-fila">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <select name="docente_id" style="width:auto;min-width:150px;font-size:.85rem">
                <?php foreach ($docentes as $d): ?>
                  <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === (int) $g['docente_id'] ? 'selected' : '' ?>>
                    <?= h(trim($d['apellidos'] . ', ' . $d['nombre'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-xs btn-ghost" name="accion" value="reasignar">Reasignar</button>
              <button class="btn btn-xs btn-ghost" name="accion" value="estado"
                      onclick="this.form.valor.value='<?= $g['estado'] === 'activo' ? 'archivado' : 'activo' ?>'">
                <?= $g['estado'] === 'activo' ? 'Archivar' : 'Reactivar' ?>
              </button>
              <button class="btn btn-xs btn-err" name="accion" value="eliminar"
                      data-confirmar="Se eliminará el grupo con sus asignaciones y entregas. ¿Continuar?">Eliminar</button>
              <input type="hidden" name="valor" value="activo">
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pie(); ?>
