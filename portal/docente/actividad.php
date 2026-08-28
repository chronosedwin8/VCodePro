<?php
/**
 * Ficha completa de una actividad del banco, con guía docente y asignación.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$act = actividad_completa(get_int('id'));
if (!$act) { flash_err('Actividad no encontrada.'); redirigir('portal/docente/banco.php'); }

$grupoPre = get_int('grupo');

if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'asignar') {
        $grupoId = post_int('grupo_id');
        $g = fila('SELECT * FROM grupos WHERE id = ?', [$grupoId]);
        if (!$g || !puede_gestionar_grupo($g)) {
            flash_err('No puedes asignar a ese grupo.');
        } elseif (valor('SELECT id FROM asignaciones WHERE grupo_id = ? AND actividad_id = ?', [$grupoId, $act['id']])) {
            flash_err('La actividad ya está asignada a ' . $g['nombre'] . '.');
        } else {
            $entrega = post('fecha_entrega') ?: date('Y-m-d', strtotime('+14 days'));
            $aid = insertar('asignaciones', [
                'grupo_id'      => $grupoId,
                'actividad_id'  => (int) $act['id'],
                'docente_id'    => (int) $g['docente_id'],
                'fecha_inicio'  => post('fecha_inicio') ?: date('Y-m-d'),
                'fecha_entrega' => $entrega,
                'instrucciones' => post('instrucciones') ?: null,
                'ia_permitida'  => isset($_POST['ia_permitida']) ? 1 : 0,
                'estado'        => 'abierta',
            ]);
            $n = sembrar_entregas($aid, $grupoId);
            foreach (filas('SELECT estudiante_id FROM grupo_estudiantes WHERE grupo_id = ? AND estado = "activo"', [$grupoId]) as $e) {
                notificar((int) $e['estudiante_id'], 'Nueva actividad: ' . $act['titulo'],
                    'Entrega el ' . fecha($entrega) . '.', 'portal/estudiante/actividades.php');
            }
            auditar('actividad_asignada', 'asignaciones', $aid, $act['codigo']);
            flash_ok('Asignada a ' . $g['nombre'] . ' para ' . $n . ' estudiante(s).');
            redirigir('portal/docente/asignaciones.php?id=' . $aid);
        }
    }
}

$grupos = filas('SELECT g.id, g.nombre, g.nivel_id, n.grado FROM grupos g
                   JOIN niveles n ON n.id = g.nivel_id
                  WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ?') . ' AND g.estado = "activo"
               ORDER BY g.nivel_id = ' . (int) $act['nivel_id'] . ' DESC, g.nombre',
                es('admin') ? [] : [$u['id']]);

$yaAsignada = filas('SELECT a.id, g.nombre, a.fecha_entrega FROM asignaciones a
                       JOIN grupos g ON g.id = a.grupo_id
                      WHERE a.actividad_id = ? AND ' . (es('admin') ? '1=1' : 'a.docente_id = ?'),
                    es('admin') ? [$act['id']] : [$act['id'], $u['id']]);

$totalMin = array_sum(array_map(fn($f) => (int) $f['minutos'], $act['fases']));

cabecera($act['titulo'], [
    'titulo' => $act['titulo'],
    'sub'    => $act['resumen'],
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Banco', 'portal/docente/banco.php'], [$act['codigo']]],
    'acciones' => '<span class="chip chip-gris">' . h($act['grado']) . '</span> <span class="chip chip-crit">Criterios ' . h($act['criterios_ib']) . '</span>',
]);
?>
<div class="rejilla rej-lat">
  <div>
    <div class="panel">
      <div class="panel-h"><h2>Descripción</h2><p><?= (int) $act['sesiones'] ?> sesiones · <?= (float) $act['horas'] ?> h · <?= $totalMin ?> min de trabajo guiado</p></div>
      <div class="prosa"><?= $act['descripcion'] ?></div>
      <?php if ($act['pregunta_indagacion']): ?>
        <p class="mb-0" style="border-left:3px solid var(--brand);background:var(--bg-alt);padding:12px 14px;border-radius:0 8px 8px 0">
          <strong>Pregunta de indagación.</strong> <?= h($act['pregunta_indagacion']) ?>
        </p>
      <?php endif; ?>
      <div class="rejilla rej-2 mt-2">
        <div>
          <p class="campo-label">Objetivos</p>
          <ul class="txt-sm"><?php foreach (lista($act['objetivos']) as $o): ?><li><?= h($o) ?></li><?php endforeach; ?></ul>
        </div>
        <div>
          <p class="campo-label">Entregables</p>
          <ul class="txt-sm"><?php foreach (lista($act['entregables']) as $o): ?><li><?= h($o) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h"><h2>Secuencia del ciclo de diseño</h2><p>Guía de sesiones para el aula</p></div>
      <div class="fases">
        <?php foreach ($act['fases'] as $i => $f): ?>
          <details class="fase" open>
            <summary>
              <span class="fase-n"><?= $i + 1 ?></span>
              <span><?= h(nombre_fase($f['fase'])) ?> · <?= h($f['titulo']) ?></span>
              <span class="fase-tag"><?= (int) $f['minutos'] ?> min</span>
            </summary>
            <div class="fase-cuerpo">
              <p><?= nl($f['instrucciones']) ?></p>
              <p class="entregable"><strong>Evidencia esperada:</strong> <?= h($f['entregable']) ?></p>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h"><h2>Rúbrica</h2><p>Total <?= array_sum(array_map(fn($c) => (int) $c['maximo'], $act['rubrica'])) ?> puntos</p></div>
      <div class="rubrica">
        <?php foreach ($act['rubrica'] as $c): ?>
          <div class="rub-crit">
            <header>
              <span class="rub-letra"><?= h($c['criterio']) ?></span>
              <div><strong><?= h($c['nombre']) ?></strong><br><span class="txt-sm txt-muted">Máximo <?= (int) $c['maximo'] ?> puntos</span></div>
            </header>
            <div class="rub-niveles">
              <div><b>1–2</b><?= h($c['descriptor_12']) ?></div>
              <div><b>3–4</b><?= h($c['descriptor_34']) ?></div>
              <div><b>5–6</b><?= h($c['descriptor_56']) ?></div>
              <div><b>7–8</b><?= h($c['descriptor_78']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="asignar">
      <div class="panel-h"><h3>Asignar a un grupo</h3></div>
      <?php if (!$grupos): ?>
        <p class="txt-muted">Primero crea un grupo.</p>
        <a class="btn btn-block" href="<?= url('portal/docente/grupos.php?nuevo=1') ?>">Crear grupo</a>
      <?php else: ?>
        <div class="campo">
          <label for="grupo_id">Grupo</label>
          <select id="grupo_id" name="grupo_id" required>
            <?php foreach ($grupos as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= $grupoPre === (int) $g['id'] ? 'selected' : '' ?>>
                <?= h($g['nombre']) ?><?= (int) $g['nivel_id'] !== (int) $act['nivel_id'] ? ' (otro nivel)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-fila">
          <div class="campo">
            <label for="fecha_inicio">Inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="campo">
            <label for="fecha_entrega">Entrega</label>
            <input type="date" id="fecha_entrega" name="fecha_entrega" value="<?= date('Y-m-d', strtotime('+' . max(7, (int) $act['sesiones'] * 7) . ' days')) ?>">
          </div>
        </div>
        <div class="campo">
          <label for="instrucciones">Indicaciones para el grupo</label>
          <textarea id="instrucciones" name="instrucciones" style="min-height:90px" placeholder="Trabajo individual o en parejas, materiales, condiciones de entrega…"></textarea>
        </div>
        <label class="check"><input type="checkbox" name="ia_permitida" value="1" <?= $act['ia_sugerida'] ? 'checked' : '' ?>><span>Permitir asistencia de IA</span></label>
        <button class="btn btn-block" type="submit">Asignar actividad</button>
      <?php endif; ?>

      <?php if ($yaAsignada): ?>
        <p class="campo-label mt-2">Ya asignada en</p>
        <ul class="txt-sm" style="padding-left:1rem">
          <?php foreach ($yaAsignada as $y): ?>
            <li><a href="<?= url('portal/docente/asignaciones.php?id=' . (int) $y['id']) ?>"><?= h($y['nombre']) ?></a> · <?= fecha($y['fecha_entrega']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Ficha IB</h3></div>
      <dl class="dl">
        <dt>Código</dt><dd class="mono"><?= h($act['codigo']) ?></dd>
        <dt>Nivel</dt><dd><?= h($act['grado']) ?> · <?= h($act['programa_ib']) ?></dd>
        <dt>Contexto global</dt><dd><?= h($act['contexto_global']) ?></dd>
        <dt>Concepto clave</dt><dd><?= h($act['concepto_clave']) ?></dd>
        <dt>Dificultad</dt><dd><?= h(ucfirst($act['dificultad'])) ?></dd>
        <dt>Lenguaje</dt><dd><?= h($act['lenguaje']) ?></dd>
        <dt>IA sugerida</dt><dd><?= $act['ia_sugerida'] ? 'Sí, en modo pedagógico' : 'No para esta actividad' ?></dd>
      </dl>
      <p class="campo-label mt-2">Perfil de la comunidad</p>
      <div class="act-meta"><?php foreach (lista($act['perfil_ib']) as $p): ?><span class="chip chip-azul"><?= h($p) ?></span><?php endforeach; ?></div>
      <p class="campo-label mt-2">Enfoques del aprendizaje</p>
      <div class="act-meta"><?php foreach (lista($act['atl']) as $p): ?><span class="chip chip-gris"><?= h($p) ?></span><?php endforeach; ?></div>
    </div>

    <?php if ($act['recursos']): ?>
    <div class="panel">
      <div class="panel-h"><h3>Recursos del docente</h3></div>
      <ul class="txt-sm" style="padding-left:1rem">
        <?php foreach ($act['recursos'] as $r): ?>
          <li style="margin-bottom:.5rem"><strong><?= h($r['titulo']) ?></strong> <span class="chip chip-gris"><?= h($r['tipo']) ?></span><br><span class="txt-muted"><?= h($r['detalle']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($act['codigo_inicial']): ?>
    <div class="panel">
      <div class="panel-h"><h3>Código de inicio</h3></div>
      <pre class="codigo"><?= h($act['codigo_inicial']) ?></pre>
    </div>
    <?php endif; ?>
  </aside>
</div>
<?php pie(); ?>
