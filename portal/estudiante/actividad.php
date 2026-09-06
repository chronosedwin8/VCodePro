<?php
/**
 * Espacio de trabajo del estudiante sobre una actividad:
 * ciclo de diseño por fases, bitácora, entrega y retroalimentación.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/adjuntos.php';

$u = exigir_rol('estudiante');
$entregaId = get_int('e');

$e = fila('SELECT e.*, a.id AS asignacion_id, a.fecha_inicio, a.fecha_entrega, a.instrucciones,
                  a.estado AS estado_asignacion, a.ia_permitida, a.actividad_id,
                  g.nombre AS grupo, g.modo_examen,
                  CONCAT(d.nombre, " ", d.apellidos) AS docente
             FROM entregas e
             JOIN asignaciones a ON a.id = e.asignacion_id
             JOIN grupos g ON g.id = a.grupo_id
             JOIN usuarios d ON d.id = a.docente_id
            WHERE e.id = ? AND e.estudiante_id = ?', [$entregaId, $u['id']]);

if (!$e) { flash_err('No encontramos esa actividad entre las tuyas.'); redirigir('portal/estudiante/actividades.php'); }

$act = actividad_completa((int) $e['actividad_id']);
$adjuntos = adjuntos_por_fase($entregaId);
$bloqueada = $e['estado'] === 'revisada' || $e['estado_asignacion'] === 'cerrada';

// ------------------------------------------------------------- acciones --
if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    if ($accion === 'entregar' && !$bloqueada) {
        $archivo = null; $nombreArchivo = null;
        if (!empty($_FILES['archivo']['name'])) {
            $sub = guardar_subida($_FILES['archivo'], 'entregas');
            if ($sub) [$archivo, $nombreArchivo] = $sub;
        }
        $datos = [
            'texto'        => post_rico('texto'),
            'url_repo'     => post('url_repo') ?: null,
            'uso_ia'       => post_rico('uso_ia') ?: null,
            'estado'       => 'entregada',
            'entregado_en' => date('Y-m-d H:i:s'),
        ];
        if ($archivo) { $datos['archivo'] = $archivo; $datos['archivo_nombre'] = $nombreArchivo; }
        if ($e['estado'] === 'rehacer') $datos['intento'] = (int) $e['intento'] + 1;

        actualizar('entregas', $datos, 'id = :id', ['id' => $entregaId]);
        auditar('entrega_enviada', 'entregas', $entregaId, $act['codigo']);
        notificar((int) valor('SELECT docente_id FROM asignaciones WHERE id = ?', [$e['asignacion_id']]),
            'Entrega recibida: ' . $act['titulo'],
            nombre_completo($u) . ' entregó la actividad ' . $act['codigo'] . '.',
            'portal/docente/calificar_entrega.php?e=' . $entregaId);
        revisar_insignias($u['id']);
        flash_ok('Entrega enviada. Tu docente la verá en su lista de revisión.');
        redirigir('portal/estudiante/actividad.php?e=' . $entregaId);
    }

    if ($accion === 'guardar_borrador' && !$bloqueada) {
        actualizar('entregas', [
            'texto'    => post_rico('texto'),
            'url_repo' => post('url_repo') ?: null,
            'uso_ia'   => post_rico('uso_ia') ?: null,
            'estado'   => $e['estado'] === 'pendiente' ? 'en_progreso' : $e['estado'],
        ], 'id = :id', ['id' => $entregaId]);
        flash_ok('Borrador guardado.');
        redirigir('portal/estudiante/actividad.php?e=' . $entregaId);
    }

    if ($accion === 'bitacora') {
        $titulo = post('titulo');
        if ($titulo === '') {
            flash_err('La entrada de bitácora necesita un título.');
        } else {
            insertar('bitacora', [
                'estudiante_id' => $u['id'],
                'entrega_id'    => $entregaId,
                'fase'          => in_array(post('fase'), array_keys(FASES_CICLO), true) ? post('fase') : 'general',
                'titulo'        => $titulo,
                'contenido'     => post_rico('contenido'),
                'minutos'       => post_int('minutos'),
            ]);
            revisar_insignias($u['id']);
            flash_ok('Entrada registrada en tu bitácora.');
        }
        redirigir('portal/estudiante/actividad.php?e=' . $entregaId . '#bitacora');
    }

    if ($accion === 'comentar') {
        $m = post_rico('mensaje');
        if ($m !== '') {
            insertar('comentarios', ['entrega_id' => $entregaId, 'autor_id' => $u['id'], 'mensaje' => $m]);
            notificar((int) valor('SELECT docente_id FROM asignaciones WHERE id = ?', [$e['asignacion_id']]),
                'Pregunta de ' . $u['nombre'], rico_plano($m, 120),
                'portal/docente/calificar_entrega.php?e=' . $entregaId);
            flash_ok('Mensaje enviado a tu docente.');
        }
        redirigir('portal/estudiante/actividad.php?e=' . $entregaId . '#conversacion');
    }
}

// --------------------------------------------------------------- datos ---
$e = fila('SELECT * FROM entregas WHERE id = ?', [$entregaId]) + $e;
$contenidoFases = [];
foreach (filas('SELECT * FROM entrega_fases WHERE entrega_id = ?', [$entregaId]) as $f) {
    $contenidoFases[(int) $f['fase_id']] = $f;
}
$calificaciones = [];
foreach (filas('SELECT * FROM calificaciones WHERE entrega_id = ?', [$entregaId]) as $c) {
    $calificaciones[(int) $c['criterio_id']] = $c;
}
$comentarios = filas('SELECT c.*, u.nombre, u.apellidos, u.rol FROM comentarios c
                        JOIN usuarios u ON u.id = c.autor_id
                       WHERE c.entrega_id = ? AND (c.privado = 0 OR c.autor_id = ?)
                    ORDER BY c.id ASC', [$entregaId, $u['id']]);
$bitacora = filas('SELECT * FROM bitacora WHERE entrega_id = ? ORDER BY id DESC', [$entregaId]);
$dias = dias_para($e['fecha_entrega']);
[$obtenido, $maximo] = [array_sum(array_column($calificaciones, 'puntaje')), array_sum(array_column($act['rubrica'], 'maximo'))];

cabecera($act['titulo'], [
    'titulo' => $act['titulo'],
    'sub'    => $act['resumen'],
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Mis actividades', 'portal/estudiante/actividades.php'], [$act['codigo']]],
    'acciones' => etiqueta_estado($e['estado'])
        . ' <span class="chip chip-gris">Entrega ' . fecha($e['fecha_entrega']) . '</span>',
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if ($e['estado'] === 'rehacer'): ?>
      <div class="aviso aviso-warn"><div><strong>Tu docente pidió ajustes.</strong> Revisa la retroalimentación al final de la página, corrige y vuelve a entregar. Este será el intento <?= (int) $e['intento'] + 1 ?>.</div></div>
    <?php elseif ($dias < 0 && in_array($e['estado'], ['pendiente', 'en_progreso'], true)): ?>
      <div class="aviso aviso-err"><div><strong>La fecha de entrega ya pasó</strong> hace <?= abs($dias) ?> días. Habla con tu docente antes de continuar.</div></div>
    <?php elseif ($e['modo_examen']): ?>
      <div class="aviso aviso-info"><div><strong>Modo examen activo:</strong> la asistencia de IA está desactivada para este grupo.</div></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-h">
        <h2>La actividad</h2>
        <span class="chip chip-crit">Criterios <?= h($act['criterios_ib']) ?></span>
      </div>
      <?= bloque_rico($act['descripcion'], 'prosa') ?>
      <?php if ($act['pregunta_indagacion']): ?>
        <p class="mb-0" style="border-left:3px solid var(--brand);background:var(--bg-alt);padding:12px 14px;border-radius:0 8px 8px 0">
          <strong>Pregunta de indagación.</strong> <?= h($act['pregunta_indagacion']) ?>
        </p>
      <?php endif; ?>
      <?php if ($e['instrucciones']): ?>
        <p class="campo-label mt-2">Indicaciones de tu docente</p>
        <?= bloque_rico($e['instrucciones']) ?>
      <?php endif; ?>

      <div class="rejilla rej-2 mt-2">
        <div>
          <p class="campo-label">Objetivos de aprendizaje</p>
          <ul class="txt-sm"><?php foreach (lista($act['objetivos']) as $o): ?><li><?= h($o) ?></li><?php endforeach; ?></ul>
        </div>
        <div>
          <p class="campo-label">Qué debes entregar</p>
          <ul class="txt-sm"><?php foreach (lista($act['entregables']) as $o): ?><li><?= h($o) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    </div>

    <!-- ============================ CICLO DE DISEÑO ============================ -->
    <div class="panel">
      <div class="panel-h">
        <h2>Ciclo de diseño</h2>
        <p><span data-progreso-texto><?= (int) $e['progreso'] ?></span>% completado · se guarda solo</p>
      </div>
      <div data-progreso role="progressbar" aria-valuenow="<?= (int) $e['progreso'] ?>" aria-valuemin="0" aria-valuemax="100" class="barra mb-2"><i style="width:<?= (int) $e['progreso'] ?>%"></i></div>

      <div class="fases">
        <?php foreach ($act['fases'] as $i => $f):
            $ef = $contenidoFases[(int) $f['id']] ?? null;
            $hecha = $ef && $ef['completada'];
        ?>
          <details class="fase" data-hecha="<?= $hecha ? 1 : 0 ?>" <?= (!$hecha && !$bloqueada) ? 'open' : '' ?>>
            <summary>
              <span class="fase-n"><?= $hecha ? '✓' : $i + 1 ?></span>
              <span><?= h(nombre_fase($f['fase'])) ?> · <?= h($f['titulo']) ?></span>
              <span class="fase-tag"><?= (int) $f['minutos'] ?> min</span>
            </summary>
            <div class="fase-cuerpo">
              <?= bloque_rico($f['instrucciones']) ?>
              <p class="entregable"><strong>Evidencia de esta fase:</strong> <?= h($f['entregable']) ?></p>

              <div class="campo mt-2">
                <label for="fase<?= (int) $f['id'] ?>">Tu trabajo en esta fase</label>
                <textarea id="fase<?= (int) $f['id'] ?>" data-rico
                          <?= $bloqueada ? 'disabled' : '' ?>
                          data-fase="<?= (int) $f['id'] ?>"
                          data-entrega="<?= $entregaId ?>"
                          data-csrf="<?= h(csrf_token()) ?>"
                          data-url="<?= url('portal/api/fase.php') ?>"
                          placeholder="Escribe aquí lo que hiciste, lo que encontraste y lo que decidiste."><?= h($ef['contenido'] ?? '') ?></textarea>
                <span class="pista" data-estado-fase="<?= (int) $f['id'] ?>"></span>
              </div>

              <?= bloque_adjuntos($entregaId, (int) $f['id'], $adjuntos[(int) $f['id']] ?? [],
                                  !$bloqueada, $u, $bloqueada, 'Archivos de esta fase') ?>

              <?php if (!$bloqueada): ?>
                <label class="check">
                  <input type="checkbox"
                         data-completar="<?= (int) $f['id'] ?>"
                         data-entrega="<?= $entregaId ?>"
                         data-csrf="<?= h(csrf_token()) ?>"
                         data-url="<?= url('portal/api/fase.php') ?>"
                         <?= $hecha ? 'checked' : '' ?>>
                  <span>Marcar esta fase como terminada</span>
                </label>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- =============================== ENTREGA =============================== -->
    <div class="panel" id="entrega">
      <div class="panel-h">
        <h2>Entrega final</h2>
        <?php if ($e['entregado_en']): ?><p>Enviada el <?= fecha($e['entregado_en'], true) ?> · intento <?= (int) $e['intento'] ?></p><?php endif; ?>
      </div>

      <?php if ($bloqueada && $e['estado'] === 'revisada'): ?>
        <div class="aviso aviso-ok"><div>Esta actividad ya fue calificada. Puedes consultar tu trabajo, pero no modificarlo.</div></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" data-avisar>
        <?= csrf_campo() ?>
        <div class="campo">
          <label for="texto">Descripción de tu solución</label>
          <textarea id="texto" name="texto" data-rico <?= $bloqueada ? 'disabled' : '' ?>
                    placeholder="Explica qué construiste, cómo funciona y qué decisiones tomaste."><?= h($e['texto']) ?></textarea>
        </div>
        <div class="campo-fila">
          <div class="campo">
            <label for="url_repo">Enlace al repositorio o al producto</label>
            <input type="url" id="url_repo" name="url_repo" value="<?= h($e['url_repo']) ?>" <?= $bloqueada ? 'disabled' : '' ?> placeholder="https://">
          </div>
          <?php if ($e['archivo']): ?>
          <div class="campo">
            <span class="campo-label">Adjunto anterior</span>
            <span class="pista"><a href="<?= URL_SUBIDAS . '/' . h($e['archivo']) ?>" target="_blank" rel="noopener"><?= h($e['archivo_nombre']) ?></a></span>
          </div>
          <?php endif; ?>
        </div>
        <div class="campo">
          <label for="uso_ia">Declaración de uso de inteligencia artificial</label>
          <textarea id="uso_ia" name="uso_ia" data-rico style="min-height:90px" <?= $bloqueada ? 'disabled' : '' ?>
                    placeholder="Indica qué herramienta usaste, para qué y qué parte del trabajo es tuya. Si no usaste IA, escríbelo."><?= h($e['uso_ia']) ?></textarea>
          <span class="pista">La probidad académica exige declarar el uso de estas herramientas, no evitarlas.</span>
        </div>

        <div class="campo">
          <span class="campo-label">Archivos de la entrega</span>
          <?= bloque_adjuntos($entregaId, null, $adjuntos[0] ?? [], !$bloqueada, $u, $bloqueada) ?>
        </div>
        <?php if (!$bloqueada): ?>
          <div class="form-acc">
            <button class="btn" name="accion" value="entregar" data-confirmar="¿Enviar la entrega a tu docente?">Enviar entrega</button>
            <button class="btn btn-ghost" name="accion" value="guardar_borrador">Guardar borrador</button>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- ============================ CALIFICACIÓN ============================= -->
    <?php if ($calificaciones): ?>
    <div class="panel" id="calificacion">
      <div class="panel-h">
        <h2>Calificación por criterio</h2>
        <p><?= $obtenido ?> de <?= $maximo ?> puntos<?= $e['nota_letra'] ? ' · nota ' . h($e['nota_letra']) : '' ?></p>
      </div>
      <div class="rubrica">
        <?php foreach ($act['rubrica'] as $c):
            $cal = $calificaciones[(int) $c['id']] ?? null;
            $p = $cal ? (int) $cal['puntaje'] : null;
        ?>
          <div class="rub-crit">
            <header>
              <span class="rub-letra"><?= h($c['criterio']) ?></span>
              <div>
                <strong><?= h($c['nombre']) ?></strong><br>
                <span class="txt-sm txt-muted"><?= $p === null ? 'Sin calificar' : "$p de {$c['maximo']} puntos" ?></span>
              </div>
            </header>
            <?php if ($p !== null): ?>
              <?= barra(porcentaje((float) $p, (float) $c['maximo']), $p >= $c['maximo'] * 0.75 ? 'ok' : ($p >= $c['maximo'] * 0.5 ? '' : 'warn')) ?>
              <?php if (!empty($cal['comentario'])): ?>
                <?= bloque_rico($cal['comentario'], 'txt-sm mt-1') ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ============================= CONVERSACIÓN ============================ -->
    <div class="panel" id="conversacion">
      <div class="panel-h"><h2>Conversación con tu docente</h2></div>
      <?php if ($comentarios): ?>
        <ul class="linea">
          <?php foreach ($comentarios as $c): ?>
            <li>
              <h4><?= h(trim($c['nombre'] . ' ' . $c['apellidos'])) ?> <span class="chip chip-gris"><?= h(ROLES[$c['rol']] ?? '') ?></span></h4>
              <time><?= fecha($c['creado_en'], true) ?></time>
              <?= bloque_rico($c['mensaje']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="txt-muted">Todavía no hay mensajes. Si algo de la actividad no te queda claro, pregunta aquí.</p>
      <?php endif; ?>
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="comentar">
        <div class="campo">
          <label for="mensaje">Escribir un mensaje</label>
          <textarea id="mensaje" name="mensaje" data-rico style="min-height:90px" required></textarea>
        </div>
        <button class="btn btn-sm" type="submit">Enviar</button>
      </form>
    </div>
  </div>

  <!-- ================================ LATERAL =============================== -->
  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Ficha IB</h3></div>
      <dl class="dl">
        <dt>Código</dt><dd class="mono"><?= h($act['codigo']) ?></dd>
        <dt>Nivel</dt><dd><?= h($act['grado']) ?> · <?= h($act['programa_ib']) ?></dd>
        <dt>Contexto global</dt><dd><?= h($act['contexto_global']) ?></dd>
        <dt>Concepto clave</dt><dd><?= h($act['concepto_clave']) ?></dd>
        <dt>Lenguaje</dt><dd><?= h($act['lenguaje']) ?></dd>
        <dt>Duración</dt><dd><?= (int) $act['sesiones'] ?> sesiones · <?= (float) $act['horas'] ?> h</dd>
        <dt>Grupo</dt><dd><?= h($e['grupo']) ?></dd>
        <dt>Docente</dt><dd><?= h($e['docente']) ?></dd>
      </dl>
      <p class="campo-label mt-2">Perfil de la comunidad</p>
      <div class="act-meta">
        <?php foreach (lista($act['perfil_ib']) as $p): ?><span class="chip chip-azul"><?= h($p) ?></span><?php endforeach; ?>
      </div>
      <p class="campo-label mt-2">Enfoques del aprendizaje</p>
      <div class="act-meta">
        <?php foreach (lista($act['atl']) as $p): ?><span class="chip chip-gris"><?= h($p) ?></span><?php endforeach; ?>
      </div>
    </div>

    <?php if ($act['codigo_inicial']): ?>
    <div class="panel">
      <div class="panel-h"><h3>Código de inicio</h3></div>
      <pre class="codigo"><?= h($act['codigo_inicial']) ?></pre>
    </div>
    <?php endif; ?>

    <?php if ($act['recursos']): ?>
    <div class="panel">
      <div class="panel-h"><h3>Recursos</h3></div>
      <ul class="txt-sm" style="padding-left:1rem">
        <?php foreach ($act['recursos'] as $r): ?>
          <li style="margin-bottom:.5rem">
            <strong><?= h($r['titulo']) ?></strong> <span class="chip chip-gris"><?= h($r['tipo']) ?></span><br>
            <span class="txt-muted"><?= h($r['detalle']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-h"><h3>Cómo se califica</h3></div>
      <?php foreach ($act['rubrica'] as $c): ?>
        <details class="mb-0" style="border-bottom:1px solid var(--border-soft);padding:.5rem 0">
          <summary style="cursor:pointer"><strong><?= h($c['criterio']) ?>.</strong> <?= h($c['nombre']) ?> <span class="txt-muted txt-sm">(<?= (int) $c['maximo'] ?> pts)</span></summary>
          <div class="rub-niveles mt-1">
            <div><b>1–2</b><?= h($c['descriptor_12']) ?></div>
            <div><b>3–4</b><?= h($c['descriptor_34']) ?></div>
            <div><b>5–6</b><?= h($c['descriptor_56']) ?></div>
            <div><b>7–8</b><?= h($c['descriptor_78']) ?></div>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="panel" id="bitacora">
      <div class="panel-h"><h3>Bitácora de esta actividad</h3></div>
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="bitacora">
        <div class="campo">
          <label for="btitulo">Qué hiciste hoy</label>
          <input type="text" id="btitulo" name="titulo" required placeholder="Ej.: Corregí la validación del formulario">
        </div>
        <div class="campo-fila">
          <div class="campo">
            <label for="bfase">Fase</label>
            <select id="bfase" name="fase">
              <?php foreach (FASES_CICLO as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="bminutos">Minutos</label>
            <input type="number" id="bminutos" name="minutos" min="0" max="600" value="45">
          </div>
        </div>
        <div class="campo">
          <label for="bcontenido">Detalle</label>
          <textarea id="bcontenido" name="contenido" data-rico style="min-height:90px" placeholder="Qué no funcionó, qué decidiste y cuál es el siguiente paso."></textarea>
        </div>
        <button class="btn btn-sm btn-block" type="submit">Registrar</button>
      </form>

      <?php if ($bitacora): ?>
        <ul class="linea mt-2">
          <?php foreach ($bitacora as $b): ?>
            <li>
              <h4><?= h($b['titulo']) ?></h4>
              <time><?= h(nombre_fase($b['fase'])) ?> · <?= fecha($b['creado_en'], true) ?> · <?= (int) $b['minutos'] ?> min</time>
              <?php if ($b['contenido']): ?><p><?= h(rico_plano($b['contenido'], 180)) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php pie(); ?>
