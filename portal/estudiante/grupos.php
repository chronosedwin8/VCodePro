<?php
/**
 * Grupos del estudiante y matrícula con código.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');

if (es_post()) {
    exigir_csrf();
    $codigo = mb_strtoupper(post('codigo'));
    $g = fila('SELECT * FROM grupos WHERE codigo = ? AND estado = "activo"', [$codigo]);
    if (!$g) {
        flash_err('El código no corresponde a ningún grupo activo.');
    } elseif (valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$g['id'], $u['id']])) {
        flash_err('Ya perteneces a ese grupo.');
    } else {
        insertar('grupo_estudiantes', ['grupo_id' => $g['id'], 'estudiante_id' => $u['id'], 'estado' => 'activo']);
        foreach (filas('SELECT id FROM asignaciones WHERE grupo_id = ? AND estado = "abierta"', [$g['id']]) as $a) {
            entrega_de((int) $a['id'], $u['id']);
        }
        if (!$u['colegio_id'] && $g['colegio_id']) {
            actualizar('usuarios', ['colegio_id' => $g['colegio_id']], 'id = :id', ['id' => $u['id']]);
        }
        notificar((int) $g['docente_id'], 'Nuevo estudiante en ' . $g['nombre'],
            nombre_completo($u) . ' se matriculó con el código del grupo.',
            'portal/docente/grupo.php?id=' . $g['id']);
        auditar('matricula', 'grupos', (int) $g['id']);
        flash_ok('Te matriculaste en ' . $g['nombre'] . '.');
    }
    redirigir('portal/estudiante/grupos.php');
}

$grupos = filas('SELECT g.*, n.nombre AS nivel, n.grado, n.programa_ib, n.asignatura,
                        CONCAT(d.nombre, " ", d.apellidos) AS docente, d.email AS docente_email,
                        (SELECT COUNT(*) FROM grupo_estudiantes x WHERE x.grupo_id = g.id AND x.estado = "activo") AS companeros,
                        (SELECT COUNT(*) FROM asignaciones a WHERE a.grupo_id = g.id) AS actividades
                   FROM grupo_estudiantes ge
                   JOIN grupos g ON g.id = ge.grupo_id
                   JOIN niveles n ON n.id = g.nivel_id
                   JOIN usuarios d ON d.id = g.docente_id
                  WHERE ge.estudiante_id = ? AND ge.estado = "activo"
               ORDER BY g.anio DESC, g.nombre', [$u['id']]);

cabecera('Mis grupos', [
    'titulo' => 'Mis grupos',
    'sub'    => 'Los cursos en los que estás matriculado este año escolar.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Mis grupos']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if (!$grupos): ?>
      <?= vacio('No estás en ningún grupo', 'Pide a tu docente el código de ocho caracteres y matricúlate desde el formulario de la derecha.') ?>
    <?php else: ?>
      <?php foreach ($grupos as $g):
          $r = resumen_grupo((int) $g['id']);
          $mias = fila('SELECT COUNT(*) AS n, ROUND(AVG(progreso)) AS avance
                          FROM entregas e JOIN asignaciones a ON a.id = e.asignacion_id
                         WHERE a.grupo_id = ? AND e.estudiante_id = ?', [$g['id'], $u['id']]);
      ?>
        <div class="panel">
          <div class="panel-h">
            <div>
              <h2><?= h($g['nombre']) ?></h2>
              <p><?= h($g['programa_ib']) ?> · <?= h($g['asignatura']) ?> · <?= h($g['periodo']) ?> de <?= (int) $g['anio'] ?></p>
            </div>
            <?php if ($g['modo_examen']): ?><span class="chip chip-ambar">Modo examen</span><?php endif; ?>
          </div>
          <dl class="dl">
            <dt>Docente</dt><dd><?= h($g['docente']) ?> · <span class="txt-muted"><?= h($g['docente_email']) ?></span></dd>
            <dt>Compañeros</dt><dd><?= (int) $g['companeros'] ?> estudiantes</dd>
            <dt>Actividades asignadas</dt><dd><?= (int) $g['actividades'] ?></dd>
            <dt>Asistencia de IA</dt><dd><?= $g['ia_permitida'] && !$g['modo_examen'] ? 'Habilitada en modo pedagógico' : 'Desactivada por el docente' ?></dd>
          </dl>
          <p class="campo-label mt-2">Mi avance en este grupo</p>
          <?= barra((int) ($mias['avance'] ?? 0)) ?>
          <span class="txt-sm txt-muted"><?= (int) ($mias['avance'] ?? 0) ?>% en <?= (int) ($mias['n'] ?? 0) ?> actividad(es)</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <div class="panel-h"><h3>Matricularme en un grupo</h3></div>
      <div class="campo">
        <label for="codigo">Código del grupo</label>
        <input type="text" id="codigo" name="codigo" maxlength="8" required
               style="text-transform:uppercase;letter-spacing:.16em;font-family:var(--mono)" placeholder="K7P2M4RD">
        <span class="pista">Ocho caracteres que entrega tu docente.</span>
      </div>
      <button class="btn btn-block" type="submit">Matricularme</button>
    </form>
  </aside>
</div>
<?php pie(); ?>
