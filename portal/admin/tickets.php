<?php
/**
 * Soporte: tickets de los clientes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');
$ver = get_int('ver');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');
    $id = post_int('id');

    if ($accion === 'responder' && $id) {
        $m = post('mensaje');
        if ($m !== '') {
            insertar('ticket_mensajes', ['ticket_id' => $id, 'autor_id' => $u['id'], 'mensaje' => $m]);
            $t = fila('SELECT cliente_id, asunto FROM tickets WHERE id = ?', [$id]);
            if ($t) {
                notificar((int) $t['cliente_id'], 'Respuesta de soporte: ' . $t['asunto'], corte($m, 120), 'portal/cliente/soporte.php?ver=' . $id);
            }
            if (valor('SELECT estado FROM tickets WHERE id = ?', [$id]) === 'abierto') {
                actualizar('tickets', ['estado' => 'en_proceso'], 'id = :id', ['id' => $id]);
            }
            flash_ok('Respuesta enviada al cliente.');
        }
    }

    if ($accion === 'estado' && $id) {
        $v = in_array(post('valor'), ['abierto', 'en_proceso', 'resuelto', 'cerrado'], true) ? post('valor') : 'abierto';
        actualizar('tickets', ['estado' => $v], 'id = :id', ['id' => $id]);
        auditar('ticket_estado', 'tickets', $id, $v);
        flash_ok('Estado del ticket actualizado.');
    }

    if ($accion === 'prioridad' && $id) {
        actualizar('tickets', ['prioridad' => post('valor')], 'id = :id', ['id' => $id]);
        flash_ok('Prioridad actualizada.');
    }
    redirigir('portal/admin/tickets.php' . ($id ? '?ver=' . $id : ''));
}

$estadoF = get('estado');
$where = ['1=1']; $params = [];
if (in_array($estadoF, ['abierto', 'en_proceso', 'resuelto', 'cerrado'], true)) { $where[] = 't.estado = ?'; $params[] = $estadoF; }

$tickets = filas('SELECT t.*, CONCAT(u.nombre, " ", u.apellidos) AS cliente, u.email, c.nombre AS colegio,
                         (SELECT COUNT(*) FROM ticket_mensajes m WHERE m.ticket_id = t.id) AS mensajes
                    FROM tickets t
                    JOIN usuarios u ON u.id = t.cliente_id
               LEFT JOIN colegios c ON c.id = u.colegio_id
                   WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(t.estado,"abierto","en_proceso","resuelto","cerrado"),
                         FIELD(t.prioridad,"alta","media","baja"), t.creado_en DESC', $params);

$detalle = $ver ? fila('SELECT t.*, CONCAT(u.nombre, " ", u.apellidos) AS cliente, u.email
                          FROM tickets t JOIN usuarios u ON u.id = t.cliente_id WHERE t.id = ?', [$ver]) : null;
$mensajes = $detalle ? filas('SELECT m.*, CONCAT(u.nombre, " ", u.apellidos) AS autor, u.rol
                                FROM ticket_mensajes m JOIN usuarios u ON u.id = m.autor_id
                               WHERE m.ticket_id = ? ORDER BY m.id', [$ver]) : [];

$tot = fila('SELECT COUNT(*) AS n, SUM(estado="abierto") AS abiertos,
                    SUM(estado="en_proceso") AS proceso, SUM(prioridad="alta" AND estado IN ("abierto","en_proceso")) AS urgentes
               FROM tickets');

cabecera('Soporte', [
    'titulo' => 'Soporte',
    'sub'    => 'Solicitudes técnicas, pedagógicas y de licenciamiento.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Soporte']],
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Tickets', (int) $tot['n']) ?>
  <?= metrica('Abiertos', (int) $tot['abiertos'], null, 'warn') ?>
  <?= metrica('En proceso', (int) $tot['proceso'], null, 'brand') ?>
  <?= metrica('Prioridad alta', (int) $tot['urgentes'], null, 'err') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Tickets</h2>
        <form method="get" class="btn-fila">
          <select name="estado" onchange="this.form.submit()">
            <option value="">Todos</option>
            <?php foreach (['abierto' => 'Abiertos', 'en_proceso' => 'En proceso', 'resuelto' => 'Resueltos', 'cerrado' => 'Cerrados'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= $estadoF === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if (!$tickets): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">No hay tickets con ese filtro.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Asunto</th><th>Cliente</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
              <tr>
                <td>
                  <strong><a href="<?= url('portal/admin/tickets.php?ver=' . (int) $t['id']) ?>"><?= h($t['asunto']) ?></a></strong><br>
                  <span class="txt-sm txt-muted"><?= (int) $t['mensajes'] ?> mensaje(s) · <?= fecha_rel($t['creado_en']) ?></span>
                </td>
                <td class="txt-sm"><?= h($t['cliente']) ?><br><span class="txt-muted"><?= h($t['colegio'] ?? $t['email']) ?></span></td>
                <td class="txt-sm"><?= h(ucfirst($t['categoria'])) ?></td>
                <td>
                  <span class="chip <?= $t['prioridad'] === 'alta' ? 'chip-rojo' : ($t['prioridad'] === 'media' ? 'chip-ambar' : 'chip-gris') ?>">
                    <?= h(ucfirst($t['prioridad'])) ?>
                  </span>
                </td>
                <td><?= etiqueta_estado($t['estado']) ?></td>
                <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/tickets.php?ver=' . (int) $t['id']) ?>">Abrir</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($detalle): ?>
    <div class="panel">
      <div class="panel-h">
        <div>
          <h2><?= h($detalle['asunto']) ?></h2>
          <p><?= h($detalle['cliente']) ?> · <?= h($detalle['email']) ?> · <?= fecha($detalle['creado_en'], true) ?></p>
        </div>
        <a class="txt-sm" href="<?= url('portal/admin/tickets.php') ?>">Cerrar</a>
      </div>
      <ul class="linea">
        <?php foreach ($mensajes as $m): ?>
          <li>
            <h4><?= h($m['autor']) ?> <span class="chip chip-gris"><?= h(ROLES[$m['rol']] ?? '') ?></span></h4>
            <time><?= fecha($m['creado_en'], true) ?></time>
            <p><?= nl($m['mensaje']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $detalle['id'] ?>">
        <div class="campo">
          <label for="mensaje">Responder</label>
          <textarea id="mensaje" name="mensaje" required></textarea>
        </div>
        <div class="form-acc">
          <button class="btn" name="accion" value="responder">Enviar respuesta</button>
          <button class="btn btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='resuelto'">Marcar resuelto</button>
          <button class="btn btn-ghost" name="accion" value="estado" onclick="this.form.valor.value='cerrado'">Cerrar</button>
          <input type="hidden" name="valor" value="resuelto">
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Cómo priorizamos</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li><strong>Alta:</strong> bloquea una clase en curso o el acceso de un grupo.</li>
        <li><strong>Media:</strong> afecta el trabajo pero tiene alternativa.</li>
        <li><strong>Baja:</strong> consulta o mejora solicitada.</li>
      </ul>
      <p class="txt-sm txt-muted mb-0">Los tickets de licencias vencidas se atienden antes que las consultas pedagógicas.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
