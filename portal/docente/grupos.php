<?php
/**
 * Grupos del docente: creación, edición y archivo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    if ($accion === 'crear') {
        $nombre = post('nombre');
        $nivel  = post_int('nivel_id');
        if ($nombre === '' || !$nivel) {
            flash_err('El nombre y el nivel son obligatorios.');
        } else {
            do { $codigo = codigo_aleatorio(8); }
            while (valor('SELECT id FROM grupos WHERE codigo = ?', [$codigo]));

            $id = insertar('grupos', [
                'colegio_id'   => $u['colegio_id'],
                'docente_id'   => $u['id'],
                'nivel_id'     => $nivel,
                'nombre'       => $nombre,
                'anio'         => post_int('anio') ?: (int) date('Y'),
                'periodo'      => post('periodo') ?: null,
                'codigo'       => $codigo,
                'jornada'      => post('jornada') ?: null,
                'ia_permitida' => isset($_POST['ia_permitida']) ? 1 : 0,
                'estado'       => 'activo',
            ]);
            auditar('grupo_creado', 'grupos', $id, $nombre);
            flash_ok('Grupo creado. El código de matrícula es ' . $codigo . '.');
            redirigir('portal/docente/grupo.php?id=' . $id);
        }
    }

    if ($accion === 'archivar' || $accion === 'reactivar') {
        $g = fila('SELECT * FROM grupos WHERE id = ?', [post_int('id')]);
        if ($g && puede_gestionar_grupo($g)) {
            actualizar('grupos', ['estado' => $accion === 'archivar' ? 'archivado' : 'activo'], 'id = :id', ['id' => $g['id']]);
            flash_ok($accion === 'archivar' ? 'Grupo archivado.' : 'Grupo reactivado.');
        }
    }

    if ($accion === 'examen') {
        $g = fila('SELECT * FROM grupos WHERE id = ?', [post_int('id')]);
        if ($g && puede_gestionar_grupo($g)) {
            $nuevo = $g['modo_examen'] ? 0 : 1;
            actualizar('grupos', ['modo_examen' => $nuevo], 'id = :id', ['id' => $g['id']]);
            auditar($nuevo ? 'modo_examen_on' : 'modo_examen_off', 'grupos', (int) $g['id']);
            flash_ok($nuevo ? 'Modo examen activado: la IA queda desactivada para el grupo.' : 'Modo examen desactivado.');
        }
    }
    redirigir('portal/docente/grupos.php');
}

$niveles = filas('SELECT * FROM niveles ORDER BY orden');
$grupos  = filas('SELECT g.*, n.nombre AS nivel, n.grado,
                         (SELECT COUNT(*) FROM grupo_estudiantes ge WHERE ge.grupo_id = g.id AND ge.estado = "activo") AS estudiantes,
                         (SELECT COUNT(*) FROM asignaciones a WHERE a.grupo_id = g.id) AS asignaciones
                    FROM grupos g JOIN niveles n ON n.id = g.nivel_id
                   WHERE g.docente_id = ?
                ORDER BY g.estado, g.anio DESC, g.nombre', [$u['id']]);

$abrirNuevo = get('nuevo') === '1' || !$grupos;

cabecera('Mis grupos', [
    'titulo' => 'Mis grupos',
    'sub'    => 'Cada grupo tiene un código de ocho caracteres con el que los estudiantes se matriculan solos.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Mis grupos']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if (!$grupos): ?>
      <?= vacio('Aún no has creado grupos', 'Crea el primero con el formulario de la derecha y comparte el código en clase.') ?>
    <?php else: ?>
      <div class="panel panel-plano">
        <div class="panel-h"><h2><?= count($grupos) ?> grupo(s)</h2></div>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Grupo</th><th>Nivel</th><th class="num">Estudiantes</th><th class="num">Actividades</th><th>Código</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($grupos as $g): ?>
              <tr>
                <td>
                  <strong><a href="<?= url('portal/docente/grupo.php?id=' . (int) $g['id']) ?>"><?= h($g['nombre']) ?></a></strong><br>
                  <span class="txt-sm txt-muted"><?= h($g['periodo']) ?> · <?= (int) $g['anio'] ?></span>
                </td>
                <td class="txt-sm"><?= h($g['grado']) ?> · <?= h($g['nivel']) ?></td>
                <td class="num"><?= (int) $g['estudiantes'] ?></td>
                <td class="num"><?= (int) $g['asignaciones'] ?></td>
                <td><code class="mono copiar" data-copiar="<?= h($g['codigo']) ?>" title="Clic para copiar"><?= h($g['codigo']) ?></code></td>
                <td>
                  <?= etiqueta_estado($g['estado']) ?>
                  <?php if ($g['modo_examen']): ?><br><span class="chip chip-ambar">Examen</span><?php endif; ?>
                </td>
                <td class="acc">
                  <form method="post" class="btn-fila">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                    <button class="btn btn-xs btn-ghost" name="accion" value="examen"><?= $g['modo_examen'] ? 'Salir de examen' : 'Modo examen' ?></button>
                    <?php if ($g['estado'] === 'activo'): ?>
                      <button class="btn btn-xs btn-ghost" name="accion" value="archivar" data-confirmar="¿Archivar el grupo? Los estudiantes dejarán de ver sus actividades.">Archivar</button>
                    <?php else: ?>
                      <button class="btn btn-xs btn-ghost" name="accion" value="reactivar">Reactivar</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="crear">
      <div class="panel-h"><h3>Nuevo grupo</h3></div>
      <div class="campo">
        <label for="nombre">Nombre del grupo</label>
        <input type="text" id="nombre" name="nombre" required placeholder="Ej.: 9.º B · Diseño" <?= $abrirNuevo ? 'autofocus' : '' ?>>
      </div>
      <div class="campo">
        <label for="nivel_id">Nivel del plan de aula</label>
        <select id="nivel_id" name="nivel_id" required>
          <?php foreach ($niveles as $n): ?>
            <option value="<?= (int) $n['id'] ?>"><?= h($n['grado'] . ' · ' . $n['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="pista">Determina qué actividades del banco puedes asignar.</span>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="anio">Año</label>
          <input type="number" id="anio" name="anio" value="<?= (int) date('Y') ?>" min="2020" max="2100">
        </div>
        <div class="campo">
          <label for="periodo">Periodo</label>
          <input type="text" id="periodo" name="periodo" value="<?= h(ajuste('periodo_actual', 'Periodo 1')) ?>">
        </div>
      </div>
      <div class="campo">
        <label for="jornada">Jornada</label>
        <input type="text" id="jornada" name="jornada" placeholder="Única, mañana, tarde…">
      </div>
      <label class="check"><input type="checkbox" name="ia_permitida" value="1" checked><span>Permitir asistencia de IA en modo pedagógico</span></label>
      <button class="btn btn-block" type="submit">Crear grupo</button>
    </form>
  </aside>
</div>
<?php pie(); ?>
