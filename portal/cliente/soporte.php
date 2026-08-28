<?php
/**
 * Soporte del cliente: tickets y conversación.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('cliente', 'admin');
$ver = get_int('ver');

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    if ($accion === 'crear') {
        $asunto = post('asunto');
        $mensaje = post('mensaje');
        if ($asunto === '' || $mensaje === '') {
            flash_err('El asunto y el mensaje son obligatorios.');
        } else {
            $tid = insertar('tickets', [
                'cliente_id' => $u['id'],
                'asunto'     => $asunto,
                'categoria'  => in_array(post('categoria'), ['tecnico', 'licencias', 'pedagogico', 'facturacion', 'otro'], true) ? post('categoria') : 'tecnico',
                'prioridad'  => in_array(post('prioridad'), ['baja', 'media', 'alta'], true) ? post('prioridad') : 'media',
                'estado'     => 'abierto',
            ]);
            insertar('ticket_mensajes', ['ticket_id' => $tid, 'autor_id' => $u['id'], 'mensaje' => $mensaje]);
            foreach (filas('SELECT id FROM usuarios WHERE rol = "admin"') as $a) {
                notificar((int) $a['id'], 'Nuevo ticket: ' . $asunto, corte($mensaje, 120), 'portal/admin/tickets.php?ver=' . $tid, 'aviso');
            }
            auditar('ticket_creado', 'tickets', $tid, $asunto);
            flash_ok('Ticket creado. Te responderemos por este mismo hilo.');
            redirigir('portal/cliente/soporte.php?ver=' . $tid);
        }
    }

    if ($accion === 'responder') {
        $id = post_int('id');
        $t = fila('SELECT * FROM tickets WHERE id = ? AND cliente_id = ?', [$id, $u['id']]);
        $m = post('mensaje');
        if ($t && $m !== '') {
            insertar('ticket_mensajes', ['ticket_id' => $id, 'autor_id' => $u['id'], 'mensaje' => $m]);
            if ($t['estado'] === 'resuelto' || $t['estado'] === 'cerrado') {
                actualizar('tickets', ['estado' => 'abierto'], 'id = :id', ['id' => $id]);
            }
            foreach (filas('SELECT id FROM usuarios WHERE rol = "admin"') as $a) {
                notificar((int) $a['id'], 'Respuesta en el ticket: ' . $t['asunto'], corte($m, 120), 'portal/admin/tickets.php?ver=' . $id);
            }
            flash_ok('Mensaje enviado.');
        }
        redirigir('portal/cliente/soporte.php?ver=' . $id);
    }

    if ($accion === 'cerrar') {
        $id = post_int('id');
        if (valor('SELECT id FROM tickets WHERE id = ? AND cliente_id = ?', [$id, $u['id']])) {
            actualizar('tickets', ['estado' => 'cerrado'], 'id = :id', ['id' => $id]);
            flash_ok('Ticket cerrado. Puedes reabrirlo escribiendo un mensaje nuevo.');
        }
        redirigir('portal/cliente/soporte.php');
    }
}

$tickets = filas('SELECT t.*, (SELECT COUNT(*) FROM ticket_mensajes m WHERE m.ticket_id = t.id) AS mensajes
                    FROM tickets t WHERE t.cliente_id = ?
                ORDER BY FIELD(t.estado,"abierto","en_proceso","resuelto","cerrado"), t.actualizado_en DESC', [$u['id']]);

$detalle = $ver ? fila('SELECT * FROM tickets WHERE id = ? AND cliente_id = ?', [$ver, $u['id']]) : null;
$mensajes = $detalle ? filas('SELECT m.*, CONCAT(u.nombre, " ", u.apellidos) AS autor, u.rol
                                FROM ticket_mensajes m JOIN usuarios u ON u.id = m.autor_id
                               WHERE m.ticket_id = ? ORDER BY m.id', [$ver]) : [];

cabecera('Soporte', [
    'titulo' => 'Soporte',
    'sub'    => 'Solicitudes técnicas, de licenciamiento y acompañamiento pedagógico.',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Soporte']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if ($detalle): ?>
      <div class="panel">
        <div class="panel-h">
          <div>
            <h2><?= h($detalle['asunto']) ?></h2>
            <p><?= h(ucfirst($detalle['categoria'])) ?> · prioridad <?= h($detalle['prioridad']) ?> · <?= fecha($detalle['creado_en'], true) ?></p>
          </div>
          <div class="btn-fila">
            <?= etiqueta_estado($detalle['estado']) ?>
            <a class="btn btn-xs btn-ghost" href="<?= url('portal/cliente/soporte.php') ?>">Cerrar detalle</a>
          </div>
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
            <button class="btn" name="accion" value="responder">Enviar</button>
            <?php if ($detalle['estado'] !== 'cerrado'): ?>
              <button class="btn btn-ghost" name="accion" value="cerrar" data-confirmar="¿Cerrar el ticket?">Cerrar ticket</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="panel panel-plano">
      <div class="panel-h"><h2>Mis solicitudes</h2></div>
      <?php if (!$tickets): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">No has abierto ninguna solicitud.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Asunto</th><th>Categoría</th><th>Prioridad</th><th>Actualizado</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
              <tr>
                <td>
                  <strong><a href="<?= url('portal/cliente/soporte.php?ver=' . (int) $t['id']) ?>"><?= h($t['asunto']) ?></a></strong><br>
                  <span class="txt-sm txt-muted"><?= (int) $t['mensajes'] ?> mensaje(s)</span>
                </td>
                <td class="txt-sm"><?= h(ucfirst($t['categoria'])) ?></td>
                <td>
                  <span class="chip <?= $t['prioridad'] === 'alta' ? 'chip-rojo' : ($t['prioridad'] === 'media' ? 'chip-ambar' : 'chip-gris') ?>">
                    <?= h(ucfirst($t['prioridad'])) ?>
                  </span>
                </td>
                <td class="txt-sm txt-muted"><?= fecha_rel($t['actualizado_en']) ?></td>
                <td><?= etiqueta_estado($t['estado']) ?></td>
                <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/cliente/soporte.php?ver=' . (int) $t['id']) ?>">Abrir</a></td>
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
      <input type="hidden" name="accion" value="crear">
      <div class="panel-h"><h3>Nueva solicitud</h3></div>
      <div class="campo">
        <label for="asunto">Asunto</label>
        <input type="text" id="asunto" name="asunto" value="<?= h(get('asunto')) ?>" required>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="categoria">Categoría</label>
          <select id="categoria" name="categoria">
            <option value="tecnico">Técnica</option>
            <option value="licencias">Licencias</option>
            <option value="pedagogico">Pedagógica</option>
            <option value="facturacion">Facturación</option>
            <option value="otro">Otra</option>
          </select>
        </div>
        <div class="campo">
          <label for="prioridad">Prioridad</label>
          <select id="prioridad" name="prioridad">
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta</option>
          </select>
        </div>
      </div>
      <div class="campo">
        <label for="nmensaje">Descripción</label>
        <textarea id="nmensaje" name="mensaje" required placeholder="Qué ocurre, en qué equipos y desde cuándo."></textarea>
      </div>
      <button class="btn btn-block" type="submit">Abrir ticket</button>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Contacto directo</h3></div>
      <p class="txt-sm mb-0">
        Correo de soporte: <a href="mailto:<?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?>"><?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?></a><br>
        <span class="txt-muted">Atención de lunes a viernes. Las solicitudes de prioridad alta se atienden el mismo día lectivo.</span>
      </p>
    </div>
  </aside>
</div>
<?php pie(); ?>
