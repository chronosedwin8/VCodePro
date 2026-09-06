<?php
/**
 * Calificación asistida de todo un grupo, desde un solo botón.
 *
 * El asistente propone; el docente revisa y publica. Son dos pasos a
 * propósito: la nota que ve el estudiante la firma una persona.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/ia_calificar.php';

$u = exigir_rol('docente', 'admin');
if (!ia_permitida($u)) {
    flash_err('El asistente de IA no está habilitado para tu cuenta. Pídeselo a la coordinación.');
    redirigir('portal/docente/calificar.php');
}

$asignacionId = get_int('asignacion');
$mio = es('admin') ? '1=1' : 'a.docente_id = ' . (int) $u['id'];

$a = fila("SELECT a.*, ac.titulo, ac.codigo, g.nombre AS grupo, g.id AS grupo_id
             FROM asignaciones a
             JOIN actividades ac ON ac.id = a.actividad_id
             JOIN grupos g ON g.id = a.grupo_id
            WHERE a.id = ? AND $mio", [$asignacionId]);
if (!$a) { flash_err('No encontramos esa asignación entre las tuyas.'); redirigir('portal/docente/asignaciones.php'); }

$act = actividad_completa((int) $a['actividad_id']);
$maximo = array_sum(array_map(fn($c) => (int) $c['maximo'], $act['rubrica'] ?? []));

// ------------------------------------------------------------- acciones --
if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    // --- Publicar: aquí es donde la propuesta se convierte en nota ---------
    if ($accion === 'publicar') {
        $ids = array_map('intval', (array) ($_POST['entrega'] ?? []));
        $n = 0;
        foreach ($ids as $eid) {
            $e = fila("SELECT e.*, ac.titulo FROM entregas e
                         JOIN asignaciones a ON a.id = e.asignacion_id
                         JOIN actividades ac ON ac.id = a.actividad_id
                        WHERE e.id = ? AND e.asignacion_id = ?", [$eid, $asignacionId]);
            if (!$e) continue;
            if (!valor('SELECT COUNT(*) FROM calificaciones WHERE entrega_id = ?', [$eid], 0)) continue;

            [$obt, $max, $nota] = calcular_nota($eid);
            actualizar('entregas', ['estado' => 'revisada'], 'id = :id', ['id' => $eid]);

            // La retroalimentación deja de ser privada: ya puede leerla.
            q('UPDATE comentarios SET privado = 0 WHERE entrega_id = ? AND privado = 1 AND mensaje LIKE ?',
              [$eid, '%<!--ia-->%']);

            notificar((int) $e['estudiante_id'], 'Calificaron tu entrega: ' . $e['titulo'],
                "Obtuviste $obt de $max puntos" . ($nota ? " (nota $nota)." : '.'),
                'portal/estudiante/actividad.php?e=' . $eid, 'logro');
            revisar_insignias((int) $e['estudiante_id']);
            auditar('entrega_calificada', 'entregas', $eid, 'publicada tras revisión de propuesta de IA');
            $n++;
        }
        flash_ok($n === 1 ? 'Se publicó 1 calificación.' : "Se publicaron $n calificaciones.");
        redirigir('portal/docente/ia_calificar.php?asignacion=' . $asignacionId);
    }

    // --- Descartar: borra la propuesta y deja la entrega como estaba -------
    if ($accion === 'descartar') {
        $ids = array_map('intval', (array) ($_POST['entrega'] ?? []));
        $n = 0;
        foreach ($ids as $eid) {
            if (!valor('SELECT id FROM entregas WHERE id = ? AND asignacion_id = ?', [$eid, $asignacionId])) continue;
            borrar('calificaciones', "entrega_id = ? AND origen = 'ia'", [$eid]);
            borrar('comentarios', 'entrega_id = ? AND privado = 1 AND mensaje LIKE ?', [$eid, '%<!--ia-->%']);
            actualizar('entregas', ['ia_calificada_en' => null], 'id = :id', ['id' => $eid]);
            auditar('ia_calificacion_descartada', 'entregas', $eid);
            $n++;
        }
        flash_ok("Se descartaron $n propuestas.");
        redirigir('portal/docente/ia_calificar.php?asignacion=' . $asignacionId);
    }
}

// ----------------------------------------------------------------- datos --
$entregas = ia_entregas_de($asignacionId);
$puntajes = [];
foreach (filas('SELECT c.entrega_id, SUM(c.puntaje) AS total, MIN(c.origen) AS origen
                  FROM calificaciones c JOIN entregas e ON e.id = c.entrega_id
                 WHERE e.asignacion_id = ? GROUP BY c.entrega_id', [$asignacionId]) as $p) {
    $puntajes[(int) $p['entrega_id']] = $p;
}

$conTrabajo  = array_filter($entregas, fn($e) => in_array($e['estado'], ['entregada', 'rehacer', 'en_progreso'], true));
$propuestas  = array_filter($entregas, fn($e) => $e['ia_calificada_en'] && $e['estado'] !== 'revisada');
$publicadas  = array_filter($entregas, fn($e) => $e['estado'] === 'revisada');

cabecera('Calificar con IA', [
    'titulo' => 'Calificación asistida',
    'sub'    => $a['codigo'] . ' · ' . $a['titulo'] . ' · ' . $a['grupo'],
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Por calificar', 'portal/docente/calificar.php'], ['Con IA']],
]);
?>

<div class="aviso aviso-info">
  <div>
    <strong>El asistente propone; tú calificas.</strong>
    Las notas y los comentarios que genere quedan guardados como propuesta y <strong>el estudiante no
    los ve</strong>. Revísalos —uno por uno si hace falta— y publícalos cuando estés de acuerdo.
    En el IB la responsabilidad de la evaluación es tuya, y en el registro queda que esta propuesta
    la hizo la máquina.
  </div>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel">
      <div class="panel-h">
        <h2>Entregas del grupo</h2>
        <p><?= count($entregas) ?> estudiantes · rúbrica de <?= $maximo ?> puntos</p>
      </div>

      <?php if (!$entregas): ?>
        <?= vacio('Sin entregas', 'Esta asignación todavía no tiene entregas.') ?>
      <?php else: ?>

      <div class="form-acc mb-2" data-ia-barra>
        <button class="btn btn-lg" type="button" data-ia-lanzar
                data-url="<?= url('portal/api/ia_calificar.php') ?>"
                data-csrf="<?= h(csrf_token()) ?>"
                data-confirmar="Se va a calificar con IA a <?= count($conTrabajo) ?> estudiantes. ¿Continuar?">
          Calificar todo el grupo con IA
        </button>
        <span class="pista" data-ia-estado role="status"></span>
      </div>
      <div class="ia-progreso" data-ia-progreso hidden><i></i></div>

      <form method="post">
        <?= csrf_campo() ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead>
              <tr>
                <th style="width:28px"><input type="checkbox" data-marcar-todo='input[name=&quot;entrega[]&quot;]' aria-label="Seleccionar todo"></th>
                <th>Estudiante</th><th>Estado</th><th class="num">Propuesta</th><th class="acc"></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($entregas as $e):
                $p = $puntajes[(int) $e['id']] ?? null;
                $esPropuesta = $e['ia_calificada_en'] && $e['estado'] !== 'revisada';
                $califica = in_array($e['estado'], ['entregada', 'rehacer', 'en_progreso'], true);
            ?>
              <tr data-fila="<?= (int) $e['id'] ?>">
                <td><?php if ($esPropuesta): ?>
                      <input type="checkbox" name="entrega[]" value="<?= (int) $e['id'] ?>" checked
                             aria-label="Seleccionar a <?= h($e['estudiante']) ?>">
                    <?php endif; ?></td>
                <td>
                  <?= h($e['estudiante']) ?>
                  <?php if ($esPropuesta): ?><br><span class="chip chip-ambar">Propuesta de IA sin publicar</span><?php endif; ?>
                </td>
                <td><?= etiqueta_estado($e['estado']) ?></td>
                <td class="num" data-celda-puntaje>
                  <?php if ($p): ?>
                    <strong><?= (int) $p['total'] ?></strong> / <?= $maximo ?>
                    <span class="txt-sm txt-muted">· nota <?= h(nota_ib((float) $p['total'], (float) $maximo)[0]) ?></span>
                  <?php else: ?><span class="txt-muted">—</span><?php endif; ?>
                </td>
                <td class="acc">
                  <a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $e['id']) ?>">Revisar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($propuestas): ?>
        <div class="form-acc mt-2">
          <button class="btn" name="accion" value="publicar"
                  data-confirmar="Se publicarán las calificaciones marcadas y los estudiantes las verán. ¿Continuar?">
            Publicar las marcadas
          </button>
          <button class="btn btn-ghost" name="accion" value="descartar"
                  data-confirmar="Se borrarán las propuestas marcadas. ¿Continuar?">
            Descartar las marcadas
          </button>
        </div>
        <?php endif; ?>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Cómo va</h3></div>
      <?= metrica('Sin calificar', (string) count($conTrabajo)) ?>
      <?= metrica('Propuestas por revisar', (string) count($propuestas)) ?>
      <?= metrica('Ya publicadas', (string) count($publicadas)) ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Qué lee el asistente</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li>La actividad, su rúbrica con los descriptores y tus indicaciones al asignarla.</li>
        <li>Lo que el estudiante escribió en cada fase del ciclo de diseño.</li>
        <li>La descripción de la solución y la declaración de uso de IA.</li>
        <li>Los adjuntos de texto y código. De un PDF, un ZIP o una hoja de cálculo solo
            sabe que existen: eso tendrás que abrirlo tú.</li>
      </ul>
      <p class="txt-sm txt-muted mb-0">Modelo: <code class="mono"><?= h(ia_modelo()) ?></code>.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
