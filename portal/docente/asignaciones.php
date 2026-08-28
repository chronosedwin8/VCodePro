<?php
/**
 * Asignaciones del docente: listado y detalle con el estado de cada estudiante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$id = get_int('id');
$mio = es('admin') ? '1=1' : 'a.docente_id = ' . (int) $u['id'];

// ================================================================ DETALLE ==
if ($id) {
    $a = fila("SELECT a.*, ac.titulo, ac.codigo, ac.resumen, ac.sesiones, ac.criterios_ib,
                      g.nombre AS grupo, g.id AS grupo_id, n.grado
                 FROM asignaciones a
                 JOIN actividades ac ON ac.id = a.actividad_id
                 JOIN grupos g ON g.id = a.grupo_id
                 JOIN niveles n ON n.id = ac.nivel_id
                WHERE a.id = ? AND $mio", [$id]);
    if (!$a) { flash_err('Asignación no encontrada.'); redirigir('portal/docente/asignaciones.php'); }

    if (es_post()) {
        exigir_csrf();
        $accion = post('accion');

        if ($accion === 'guardar') {
            actualizar('asignaciones', [
                'fecha_inicio'  => post('fecha_inicio') ?: $a['fecha_inicio'],
                'fecha_entrega' => post('fecha_entrega') ?: $a['fecha_entrega'],
                'instrucciones' => post('instrucciones') ?: null,
                'peso'          => (float) post('peso', '1'),
                'ia_permitida'  => isset($_POST['ia_permitida']) ? 1 : 0,
                'estado'        => in_array(post('estado'), ['borrador', 'abierta', 'cerrada'], true) ? post('estado') : $a['estado'],
            ], 'id = :id', ['id' => $id]);
            auditar('asignacion_actualizada', 'asignaciones', $id);
            flash_ok('Asignación actualizada.');
        }

        if ($accion === 'sembrar') {
            $n = sembrar_entregas($id, (int) $a['grupo_id']);
            flash_ok($n ? "Se crearon $n entrega(s) para estudiantes nuevos." : 'Todos los estudiantes ya tenían su entrega.');
        }

        if ($accion === 'recordar') {
            $n = 0;
            foreach (filas('SELECT estudiante_id FROM entregas WHERE asignacion_id = ? AND estado IN ("pendiente","en_progreso")', [$id]) as $e) {
                notificar((int) $e['estudiante_id'], 'Recordatorio: ' . $a['titulo'],
                    'La entrega vence el ' . fecha($a['fecha_entrega']) . '.',
                    'portal/estudiante/actividades.php', 'aviso');
                $n++;
            }
            flash_ok("Recordatorio enviado a $n estudiante(s).");
        }

        if ($accion === 'eliminar') {
            borrar('asignaciones', 'id = ?', [$id]);
            auditar('asignacion_eliminada', 'asignaciones', $id, $a['codigo']);
            flash_ok('Asignación eliminada junto con sus entregas.');
            redirigir('portal/docente/grupo.php?id=' . (int) $a['grupo_id']);
        }
        redirigir('portal/docente/asignaciones.php?id=' . $id);
    }

    $entregas = filas('SELECT e.*, CONCAT(u.apellidos, ", ", u.nombre) AS estudiante, u.email
                         FROM entregas e JOIN usuarios u ON u.id = e.estudiante_id
                        WHERE e.asignacion_id = ?
                     ORDER BY FIELD(e.estado, "entregada","rehacer","en_progreso","pendiente","revisada"), u.apellidos', [$id]);

    $res = fila('SELECT COUNT(*) AS total, SUM(estado="entregada") AS por_revisar,
                        SUM(estado="revisada") AS revisadas, SUM(estado="pendiente") AS sin_iniciar,
                        ROUND(AVG(progreso)) AS avance, ROUND(AVG(nota_final),1) AS nota
                   FROM entregas WHERE asignacion_id = ?', [$id]);

    cabecera($a['titulo'], [
        'titulo' => $a['titulo'],
        'sub'    => $a['grupo'] . ' · ' . $a['grado'] . ' · entrega ' . fecha($a['fecha_entrega']),
        'migas'  => [['Panel', 'portal/docente/index.php'], ['Asignaciones', 'portal/docente/asignaciones.php'], [$a['codigo']]],
        'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/actividad.php?id=' . (int) $a['actividad_id']) . '">Ver la actividad</a>'
                    . '<a class="btn" href="' . url('portal/docente/calificar.php?asignacion=' . $id) . '">Calificar</a>',
    ]);
    ?>
    <div class="rejilla rej-4 mb-2">
      <?= metrica('Estudiantes', (int) ($res['total'] ?? 0)) ?>
      <?= metrica('Por revisar', (int) ($res['por_revisar'] ?? 0), null, 'warn') ?>
      <?= metrica('Calificadas', (int) ($res['revisadas'] ?? 0), null, 'ok') ?>
      <?= metrica('Avance promedio', ((int) ($res['avance'] ?? 0)) . '%', $res['nota'] ? 'nota media ' . $res['nota'] . ' pts' : null, 'brand') ?>
    </div>

    <div class="rejilla rej-lat">
      <div class="panel panel-plano">
        <div class="panel-h">
          <h2>Estado por estudiante</h2>
          <input type="search" data-filtra="#tabla-entregas" placeholder="Filtrar" style="max-width:220px">
        </div>
        <div class="tabla-caja">
          <table class="tabla" id="tabla-entregas">
            <thead><tr><th>Estudiante</th><th>Estado</th><th style="min-width:130px">Avance</th><th class="num">Nota</th><th>Entregado</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($entregas as $e): ?>
              <tr>
                <td><strong><?= h($e['estudiante']) ?></strong><br><span class="txt-sm txt-muted"><?= h($e['email']) ?></span></td>
                <td><?= etiqueta_estado($e['estado']) ?><?= (int) $e['intento'] > 1 ? ' <span class="chip chip-gris">intento ' . (int) $e['intento'] . '</span>' : '' ?></td>
                <td><?= barra((int) $e['progreso']) ?><span class="txt-sm txt-muted"><?= (int) $e['progreso'] ?>%</span></td>
                <td class="num"><?= $e['nota_letra'] ? '<span class="chip chip-verde">' . h($e['nota_letra']) . '</span>' : '—' ?></td>
                <td class="txt-sm txt-muted"><?= $e['entregado_en'] ? fecha($e['entregado_en']) : '—' ?></td>
                <td class="acc"><a class="btn btn-xs <?= $e['estado'] === 'entregada' ? '' : 'btn-ghost' ?>" href="<?= url('portal/docente/calificar_entrega.php?e=' . (int) $e['id']) ?>">
                  <?= $e['estado'] === 'revisada' ? 'Revisar' : 'Calificar' ?></a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <aside>
        <form method="post" class="panel">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="guardar">
          <div class="panel-h"><h3>Ajustes de la asignación</h3></div>
          <div class="campo-fila">
            <div class="campo"><label for="fecha_inicio">Inicio</label><input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= h($a['fecha_inicio']) ?>"></div>
            <div class="campo"><label for="fecha_entrega">Entrega</label><input type="date" id="fecha_entrega" name="fecha_entrega" value="<?= h($a['fecha_entrega']) ?>"></div>
          </div>
          <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
              <?php foreach (['abierta' => 'Abierta', 'borrador' => 'Borrador', 'cerrada' => 'Cerrada'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $a['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="peso">Peso en la nota del periodo</label>
            <input type="number" id="peso" name="peso" step="0.25" min="0" max="10" value="<?= (float) $a['peso'] ?>">
          </div>
          <div class="campo">
            <label for="instrucciones">Indicaciones</label>
            <textarea id="instrucciones" name="instrucciones" style="min-height:90px"><?= h($a['instrucciones']) ?></textarea>
          </div>
          <label class="check"><input type="checkbox" name="ia_permitida" value="1" <?= $a['ia_permitida'] ? 'checked' : '' ?>><span>Permitir asistencia de IA</span></label>
          <button class="btn btn-block" type="submit">Guardar</button>
        </form>

        <form method="post" class="panel">
          <?= csrf_campo() ?>
          <div class="panel-h"><h3>Acciones</h3></div>
          <button class="btn btn-ghost btn-block mb-2" name="accion" value="sembrar">Crear entregas faltantes</button>
          <button class="btn btn-ghost btn-block mb-2" name="accion" value="recordar">Enviar recordatorio</button>
          <button class="btn btn-err btn-block" name="accion" value="eliminar"
                  data-confirmar="Se eliminará la asignación y todo el trabajo entregado por los estudiantes. ¿Continuar?">Eliminar asignación</button>
        </form>
      </aside>
    </div>
    <?php
    pie();
    exit;
}

// ================================================================ LISTADO ==
$estadoF = get('estado');
$where = [$mio]; $params = [];
if (in_array($estadoF, ['borrador', 'abierta', 'cerrada'], true)) { $where[] = 'a.estado = ?'; $params[] = $estadoF; }

$lista = filas('SELECT a.*, ac.titulo, ac.codigo, g.nombre AS grupo, n.grado,
                       (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id) AS total,
                       (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id AND e.estado = "entregada") AS por_revisar,
                       (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id AND e.estado = "revisada") AS revisadas,
                       (SELECT ROUND(AVG(e.progreso)) FROM entregas e WHERE e.asignacion_id = a.id) AS avance
                  FROM asignaciones a
                  JOIN actividades ac ON ac.id = a.actividad_id
                  JOIN grupos g ON g.id = a.grupo_id
                  JOIN niveles n ON n.id = ac.nivel_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.fecha_entrega DESC', $params);

cabecera('Asignaciones', [
    'titulo' => 'Asignaciones',
    'sub'    => 'Todo lo que has puesto a trabajar, con su avance y su fila de revisión.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Asignaciones']],
    'acciones' => '<a class="btn" href="' . url('portal/docente/banco.php') . '">Asignar del banco</a>',
]);
?>
<form class="acciones-barra" method="get">
  <select name="estado">
    <option value="">Todos los estados</option>
    <?php foreach (['abierta' => 'Abiertas', 'borrador' => 'Borradores', 'cerrada' => 'Cerradas'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <input type="search" data-filtra="#tabla-asig" placeholder="Buscar actividad o grupo" class="crece">
</form>

<?php if (!$lista): ?>
  <?= vacio('Sin asignaciones', 'Elige una actividad del banco y asígnala a uno de tus grupos.',
        '<a class="btn" href="' . url('portal/docente/banco.php') . '">Ir al banco</a>') ?>
<?php else: ?>
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla" id="tabla-asig">
        <thead><tr><th>Actividad</th><th>Grupo</th><th>Entrega</th><th style="min-width:130px">Avance</th><th class="num">Revisión</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $a): $d = dias_para($a['fecha_entrega']); ?>
          <tr>
            <td><strong><?= h($a['titulo']) ?></strong><br><span class="act-cod"><?= h($a['codigo']) ?></span></td>
            <td class="txt-sm"><?= h($a['grupo']) ?><br><span class="txt-muted"><?= h($a['grado']) ?></span></td>
            <td><?= fecha($a['fecha_entrega']) ?><br><span class="txt-sm txt-muted"><?= $d < 0 ? 'venció hace ' . abs($d) . ' d' : ($d === 0 ? 'hoy' : 'en ' . $d . ' d') ?></span></td>
            <td><?= barra((int) ($a['avance'] ?? 0)) ?><span class="txt-sm txt-muted"><?= (int) ($a['avance'] ?? 0) ?>%</span></td>
            <td class="num txt-sm">
              <?= (int) $a['revisadas'] ?>/<?= (int) $a['total'] ?>
              <?php if ((int) $a['por_revisar'] > 0): ?><br><span class="chip chip-ambar"><?= (int) $a['por_revisar'] ?> pendientes</span><?php endif; ?>
            </td>
            <td><?= etiqueta_estado($a['estado']) ?></td>
            <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/asignaciones.php?id=' . (int) $a['id']) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php pie(); ?>
