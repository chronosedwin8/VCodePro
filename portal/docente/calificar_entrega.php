<?php
/**
 * Calificación de una entrega con la rúbrica de la actividad.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/adjuntos.php';
require_once __DIR__ . '/../../includes/ia_calificar.php';

$u = exigir_rol('docente', 'admin');
$id = get_int('e');
$mio = es('admin') ? '1=1' : 'a.docente_id = ' . (int) $u['id'];

$e = fila("SELECT e.*, a.id AS asignacion_id, a.fecha_entrega, a.instrucciones, a.ia_permitida,
                  ac.id AS actividad_id, ac.titulo, ac.codigo, ac.criterios_ib,
                  g.nombre AS grupo, g.id AS grupo_id,
                  CONCAT(u2.nombre, ' ', u2.apellidos) AS estudiante, u2.email, u2.id AS estudiante_id
             FROM entregas e
             JOIN asignaciones a ON a.id = e.asignacion_id
             JOIN actividades ac ON ac.id = a.actividad_id
             JOIN grupos g ON g.id = a.grupo_id
             JOIN usuarios u2 ON u2.id = e.estudiante_id
            WHERE e.id = ? AND $mio", [$id]);

if (!$e) { flash_err('Entrega no encontrada.'); redirigir('portal/docente/calificar.php'); }

$act = actividad_completa((int) $e['actividad_id']);

// -------------------------------------------------------------- acciones --
if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    // Propuesta del asistente para esta entrega. Rellena el formulario de
    // abajo; no publica nada: el docente revisa y guarda con su firma.
    if ($accion === 'ia') {
        if (!ia_permitida($u)) {
            flash_err('El asistente de IA no está habilitado para tu cuenta.');
        } else {
            [$okIA, $rIA] = ia_calificar_entrega($id, $u);
            $okIA ? flash_ok('Propuesta lista: ' . $rIA['obtenido'] . ' de ' . $rIA['maximo']
                           . ' puntos. Revísala criterio por criterio y guarda para publicarla.')
                  : flash_err($rIA);
        }
        redirigir('portal/docente/calificar_entrega.php?e=' . $id);
    }

    if (in_array($accion, ['calificar', 'devolver'], true)) {
        foreach ($act['rubrica'] as $c) {
            $campo = 'crit_' . (int) $c['id'];
            if (!isset($_POST[$campo]) || $_POST[$campo] === '') continue;
            $puntaje = max(0, min((int) $c['maximo'], (int) $_POST[$campo]));
            $comentario = post_rico('com_' . (int) $c['id']);
            $ex = fila('SELECT id FROM calificaciones WHERE entrega_id = ? AND criterio_id = ?', [$id, $c['id']]);
            if ($ex) {
                actualizar('calificaciones', [
                    'puntaje' => $puntaje, 'comentario' => $comentario,
                    // Al guardar, el docente firma la nota: deja de ser propuesta.
                    'docente_id' => $u['id'], 'origen' => 'docente', 'fecha' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $ex['id']]);
            } else {
                insertar('calificaciones', [
                    'entrega_id' => $id, 'criterio_id' => (int) $c['id'],
                    'docente_id' => $u['id'], 'puntaje' => $puntaje, 'comentario' => $comentario,
                    'origen' => 'docente',
                ]);
            }
        }
        [$obt, $max, $nota] = calcular_nota($id);

        $nuevoEstado = $accion === 'devolver' ? 'rehacer' : 'revisada';
        actualizar('entregas', ['estado' => $nuevoEstado], 'id = :id', ['id' => $id]);

        $retro = post_rico('retroalimentacion');
        if ($retro !== '') {
            insertar('comentarios', ['entrega_id' => $id, 'autor_id' => $u['id'], 'mensaje' => $retro]);
        }

        notificar((int) $e['estudiante_id'],
            $accion === 'devolver' ? 'Debes rehacer: ' . $e['titulo'] : 'Calificaron tu entrega: ' . $e['titulo'],
            $accion === 'devolver'
                ? 'Tu docente dejó indicaciones para corregir y volver a entregar.'
                : "Obtuviste $obt de $max puntos" . ($nota ? " (nota $nota)." : '.'),
            'portal/estudiante/actividad.php?e=' . $id,
            $accion === 'devolver' ? 'aviso' : 'logro');

        revisar_insignias((int) $e['estudiante_id']);
        auditar($accion === 'devolver' ? 'entrega_devuelta' : 'entrega_calificada', 'entregas', $id, $e['codigo']);
        flash_ok($accion === 'devolver' ? 'Entrega devuelta al estudiante con tus indicaciones.' : 'Entrega calificada.');

        $siguiente = valor("SELECT e2.id FROM entregas e2
                              JOIN asignaciones a ON a.id = e2.asignacion_id
                             WHERE e2.estado = 'entregada' AND $mio AND e2.id <> ?
                          ORDER BY e2.entregado_en LIMIT 1", [$id]);
        redirigir($siguiente ? 'portal/docente/calificar_entrega.php?e=' . (int) $siguiente : 'portal/docente/calificar.php');
    }

    if ($accion === 'comentar') {
        $m = post_rico('mensaje');
        if ($m !== '') {
            insertar('comentarios', [
                'entrega_id' => $id, 'autor_id' => $u['id'], 'mensaje' => $m,
                'privado' => isset($_POST['privado']) ? 1 : 0,
            ]);
            if (empty($_POST['privado'])) {
                notificar((int) $e['estudiante_id'], 'Mensaje de tu docente', rico_plano($m, 120),
                    'portal/estudiante/actividad.php?e=' . $id);
            }
            flash_ok('Mensaje registrado.');
        }
        redirigir('portal/docente/calificar_entrega.php?e=' . $id);
    }

    if ($accion === 'reabrir') {
        actualizar('entregas', ['estado' => 'en_progreso'], 'id = :id', ['id' => $id]);
        flash_ok('Entrega reabierta: el estudiante puede volver a editarla.');
        redirigir('portal/docente/calificar_entrega.php?e=' . $id);
    }
}

// ---------------------------------------------------------------- datos ---
$fases = [];
foreach (filas('SELECT ef.*, af.id AS af_id, af.titulo, af.fase, af.entregable, af.orden
                  FROM actividad_fases af
             LEFT JOIN entrega_fases ef ON ef.fase_id = af.id AND ef.entrega_id = ?
                 WHERE af.actividad_id = ? ORDER BY af.orden', [$id, $e['actividad_id']]) as $f) {
    $fases[] = $f;
}
$adjuntos = adjuntos_por_fase($id);
$retroIA  = ia_retro_de($id);
$esPropuestaIA = $e['ia_calificada_en'] && $e['estado'] !== 'revisada';
$califs = [];
foreach (filas('SELECT * FROM calificaciones WHERE entrega_id = ?', [$id]) as $c) $califs[(int) $c['criterio_id']] = $c;

$comentarios = filas('SELECT c.*, u.nombre, u.apellidos, u.rol FROM comentarios c
                        JOIN usuarios u ON u.id = c.autor_id
                       WHERE c.entrega_id = ? ORDER BY c.id', [$id]);
$bitacora = filas('SELECT * FROM bitacora WHERE entrega_id = ? ORDER BY id DESC', [$id]);
$pendientes = (int) valor("SELECT COUNT(*) FROM entregas e2 JOIN asignaciones a ON a.id = e2.asignacion_id
                            WHERE e2.estado = 'entregada' AND $mio", [], 0);
$tarde = $e['entregado_en'] && strtotime($e['entregado_en']) > strtotime($e['fecha_entrega'] . ' 23:59:59');

cabecera('Calificar', [
    'titulo' => $e['estudiante'],
    'sub'    => $e['titulo'] . ' · ' . $e['grupo'] . ' · ' . $e['codigo'],
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Por calificar', 'portal/docente/calificar.php'], [$e['estudiante']]],
    'acciones' => etiqueta_estado($e['estado'])
        . ' <span class="chip chip-gris">' . $pendientes . ' en la fila</span>',
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if ($tarde): ?>
      <div class="aviso aviso-warn"><div>La entrega llegó fuera de plazo: <?= fecha($e['entregado_en'], true) ?> (vencía el <?= fecha($e['fecha_entrega']) ?>).</div></div>
    <?php endif; ?>

    <!-- =============================== TRABAJO ============================== -->
    <div class="panel">
      <div class="panel-h">
        <h2>Entrega del estudiante</h2>
        <p>Intento <?= (int) $e['intento'] ?> · avance <?= (int) $e['progreso'] ?>%</p>
      </div>
      <?php if ($e['texto']): ?>
        <p class="campo-label">Descripción de la solución</p>
        <?= bloque_rico($e['texto'], 'prosa') ?>
      <?php else: ?>
        <p class="txt-muted">El estudiante no escribió una descripción.</p>
      <?php endif; ?>
      <dl class="dl mt-2">
        <dt>Producto</dt>
        <dd><?= $e['url_repo'] ? '<a href="' . h($e['url_repo']) . '" target="_blank" rel="noopener">' . h($e['url_repo']) . '</a>' : '—' ?></dd>
        <dt>Archivo</dt>
        <dd><?= $e['archivo'] ? '<a href="' . URL_SUBIDAS . '/' . h($e['archivo']) . '" target="_blank" rel="noopener">' . h($e['archivo_nombre']) . '</a>' : '—' ?></dd>
        <dt>Uso de IA declarado</dt>
        <dd><?= $e['uso_ia'] ? bloque_rico($e['uso_ia']) : '<span class="txt-muted">Sin declaración</span>' ?></dd>
      </dl>

      <p class="campo-label mt-2">Archivos de la entrega</p>
      <?= bloque_adjuntos($id, null, $adjuntos[0] ?? [], true, $u, false) ?>
    </div>

    <!-- ============================ CICLO DE DISEÑO ========================= -->
    <div class="panel">
      <div class="panel-h"><h2>Trabajo por fases</h2><p>Evidencia del proceso</p></div>
      <div class="fases">
        <?php foreach ($fases as $i => $f): ?>
          <details class="fase" data-hecha="<?= $f['completada'] ? 1 : 0 ?>" <?= $f['contenido'] ? 'open' : '' ?>>
            <summary>
              <span class="fase-n"><?= $f['completada'] ? '✓' : $i + 1 ?></span>
              <span><?= h(nombre_fase($f['fase'])) ?> · <?= h($f['titulo']) ?></span>
              <span class="fase-tag"><?= $f['completada'] ? 'completada' : 'sin marcar' ?></span>
            </summary>
            <div class="fase-cuerpo">
              <p class="txt-sm txt-muted"><strong>Evidencia esperada:</strong> <?= h($f['entregable']) ?></p>
              <?php if (trim((string) $f['contenido']) !== ''): ?>
                <?= bloque_rico($f['contenido'], 'prosa') ?>
                <p class="txt-sm txt-muted mb-0">Última edición: <?= fecha($f['actualizado_en'], true) ?></p>
              <?php else: ?>
                <p class="txt-muted">Sin registro en esta fase.</p>
              <?php endif; ?>
              <?php if (!empty($adjuntos[(int) $f['af_id']])): ?>
                <?= bloque_adjuntos($id, (int) $f['af_id'], $adjuntos[(int) $f['af_id']], false, $u, true,
                                    'Archivos de esta fase') ?>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ================================ RÚBRICA ============================= -->
    <form method="post" class="panel" data-avisar>
      <?= csrf_campo() ?>
      <div class="panel-h">
        <h2>Rúbrica</h2>
        <p>Total posible <?= array_sum(array_map(fn($c) => (int) $c['maximo'], $act['rubrica'])) ?> puntos</p>
      </div>
      <?php if (ia_permitida($u) && !$esPropuestaIA && $e['estado'] !== 'revisada'): ?>
        <p class="form-acc mb-2">
          <button class="btn btn-ghost btn-sm" name="accion" value="ia" formnovalidate
                  data-confirmar="El asistente leerá esta entrega y propondrá puntajes y comentarios. Podrás revisarlos antes de guardar. ¿Continuar?">
            Proponer calificación con IA
          </button>
          <span class="pista">Tarda unos segundos. No publica nada: rellena este formulario para que lo revises.</span>
        </p>
      <?php elseif ($esPropuestaIA): ?>
        <div class="aviso aviso-warn">
          <div><span class="ia-marca">Propuesta de IA</span>
            Los puntajes y comentarios de abajo los propuso el asistente el
            <?= fecha($e['ia_calificada_en'], true) ?> y <strong>el estudiante todavía no los ve</strong>.
            Revísalos y pulsa «Guardar calificación» para hacerlos tuyos y publicarlos.</div>
        </div>
      <?php endif; ?>

      <div class="rubrica">
        <?php foreach ($act['rubrica'] as $c):
            $valor = isset($califs[(int) $c['id']]) ? (int) $califs[(int) $c['id']]['puntaje'] : null;
        ?>
          <div class="rub-crit">
            <header>
              <span class="rub-letra"><?= h($c['criterio']) ?></span>
              <div><strong><?= h($c['nombre']) ?></strong><br><span class="txt-sm txt-muted">Máximo <?= (int) $c['maximo'] ?> puntos</span></div>
            </header>
            <div class="rub-niveles mb-2">
              <div><b>1–2</b><?= h($c['descriptor_12']) ?></div>
              <div><b>3–4</b><?= h($c['descriptor_34']) ?></div>
              <div><b>5–6</b><?= h($c['descriptor_56']) ?></div>
              <div><b>7–8</b><?= h($c['descriptor_78']) ?></div>
            </div>
            <div class="rub-puntos">
              <?php for ($p = 0; $p <= (int) $c['maximo']; $p++): ?>
                <input type="radio" id="c<?= (int) $c['id'] ?>_<?= $p ?>" name="crit_<?= (int) $c['id'] ?>" value="<?= $p ?>" <?= $valor === $p ? 'checked' : '' ?>>
                <label for="c<?= (int) $c['id'] ?>_<?= $p ?>"><?= $p ?></label>
              <?php endfor; ?>
            </div>
            <div class="campo mt-1 mb-0">
              <label for="com<?= (int) $c['id'] ?>" class="txt-sm">Comentario del criterio</label>
              <textarea id="com<?= (int) $c['id'] ?>" name="com_<?= (int) $c['id'] ?>" data-rico style="min-height:70px"><?= h($califs[(int) $c['id']]['comentario'] ?? '') ?></textarea>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="campo mt-2">
        <label for="retroalimentacion">Retroalimentación general</label>
        <textarea id="retroalimentacion" name="retroalimentacion" data-rico
                  placeholder="Qué logró, qué debe mejorar y cuál es el siguiente paso concreto."><?= h($retroIA['mensaje'] ?? '') ?></textarea>
        <?php if ($retroIA): ?>
          <span class="pista">Este texto lo redactó el asistente y todavía no lo ha visto el estudiante. Ajústalo antes de guardar.</span>
        <?php endif; ?>
      </div>

      <div class="form-acc">
        <button class="btn" name="accion" value="calificar">Guardar calificación</button>
        <button class="btn btn-ghost" name="accion" value="devolver"
                data-confirmar="Se devolverá al estudiante para que corrija y vuelva a entregar. ¿Continuar?">Devolver para rehacer</button>
      </div>
    </form>

    <!-- ============================= CONVERSACIÓN =========================== -->
    <div class="panel">
      <div class="panel-h"><h2>Conversación</h2></div>
      <?php if ($comentarios): ?>
        <ul class="linea">
          <?php foreach ($comentarios as $c): ?>
            <li>
              <h4><?= h(trim($c['nombre'] . ' ' . $c['apellidos'])) ?>
                <span class="chip chip-gris"><?= h(ROLES[$c['rol']] ?? '') ?></span>
                <?php if ($c['privado']): ?><span class="chip chip-ambar">nota interna</span><?php endif; ?>
              </h4>
              <time><?= fecha($c['creado_en'], true) ?></time>
              <?= bloque_rico($c['mensaje']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="txt-muted">Sin mensajes.</p>
      <?php endif; ?>
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="comentar">
        <div class="campo">
          <label for="mensaje">Escribir</label>
          <textarea id="mensaje" name="mensaje" data-rico style="min-height:80px" required></textarea>
        </div>
        <label class="check"><input type="checkbox" name="privado" value="1"><span>Nota interna (el estudiante no la ve)</span></label>
        <button class="btn btn-sm" type="submit">Enviar</button>
      </form>
    </div>
  </div>

  <!-- ================================ LATERAL =============================== -->
  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Datos de la entrega</h3></div>
      <dl class="dl">
        <dt>Estudiante</dt><dd><?= h($e['estudiante']) ?></dd>
        <dt>Correo</dt><dd class="txt-sm"><?= h($e['email']) ?></dd>
        <dt>Grupo</dt><dd><a href="<?= url('portal/docente/grupo.php?id=' . (int) $e['grupo_id']) ?>"><?= h($e['grupo']) ?></a></dd>
        <dt>Fecha límite</dt><dd><?= fecha($e['fecha_entrega']) ?></dd>
        <dt>Recibida</dt><dd><?= $e['entregado_en'] ? fecha($e['entregado_en'], true) : 'sin entregar' ?></dd>
        <dt>Nota actual</dt><dd><?= $e['nota_letra'] ? '<span class="chip chip-verde">' . h($e['nota_letra']) . '</span> · ' . (float) $e['nota_final'] . ' pts' : '—' ?></dd>
      </dl>
      <?php if ($e['estado'] === 'revisada'): ?>
        <form method="post" class="mt-2">
          <?= csrf_campo() ?>
          <button class="btn btn-ghost btn-sm btn-block" name="accion" value="reabrir"
                  data-confirmar="¿Reabrir la entrega para que el estudiante pueda editarla?">Reabrir entrega</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Bitácora del estudiante</h3></div>
      <?php if (!$bitacora): ?>
        <p class="txt-muted mb-0">No registró bitácora en esta actividad.</p>
      <?php else: ?>
        <ul class="linea">
          <?php foreach ($bitacora as $b): ?>
            <li>
              <h4><?= h($b['titulo']) ?></h4>
              <time><?= h(nombre_fase($b['fase'])) ?> · <?= fecha($b['creado_en']) ?> · <?= (int) $b['minutos'] ?> min</time>
              <?php if ($b['contenido']): ?><p><?= h(rico_plano($b['contenido'], 160)) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Indicaciones dadas</h3></div>
      <?= $e['instrucciones'] ? bloque_rico($e['instrucciones'], 'txt-sm') : '<p class="txt-sm txt-muted mb-0">Sin indicaciones adicionales.</p>' ?>
      <p class="txt-sm txt-muted mt-2 mb-0">Asistencia de IA: <?= $e['ia_permitida'] ? 'permitida' : 'desactivada' ?>.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
