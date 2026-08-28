<?php
/**
 * Bandeja de notificaciones del usuario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

$u = exigir_login();

if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'leer_todas') {
        q('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ?', [$u['id']]);
        flash_ok('Todas las notificaciones quedaron marcadas como leídas.');
    } elseif (post('accion') === 'borrar_leidas') {
        borrar('notificaciones', 'usuario_id = ? AND leida = 1', [$u['id']]);
        flash_ok('Se eliminaron las notificaciones leídas.');
    }
    redirigir('portal/notificaciones.php');
}

// Marca como leída al abrirla desde el enlace.
$abrir = get_int('abrir');
if ($abrir) {
    $n = fila('SELECT * FROM notificaciones WHERE id = ? AND usuario_id = ?', [$abrir, $u['id']]);
    if ($n) {
        actualizar('notificaciones', ['leida' => 1], 'id = :id', ['id' => $abrir]);
        redirigir($n['url'] ?: 'portal/notificaciones.php');
    }
}

$lista = filas('SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY leida ASC, id DESC LIMIT 100', [$u['id']]);
$sinLeer = count(array_filter($lista, fn($n) => !$n['leida']));

cabecera('Notificaciones', [
    'titulo'   => 'Notificaciones',
    'sub'      => $sinLeer ? "Tienes $sinLeer sin leer." : 'No tienes notificaciones pendientes.',
    'migas'    => [['Portal', panel_de($u['rol'])], ['Notificaciones']],
    'acciones' => '<form method="post" class="btn-fila">' . csrf_campo()
                . '<button class="btn btn-ghost btn-sm" name="accion" value="leer_todas">Marcar todas como leídas</button>'
                . '<button class="btn btn-ghost btn-sm" name="accion" value="borrar_leidas" data-confirmar="¿Eliminar las notificaciones leídas?">Limpiar leídas</button>'
                . '</form>',
]);
?>
<div class="panel panel-plano">
  <?php if (!$lista): ?>
    <?= vacio('Sin notificaciones', 'Aquí aparecerán las asignaciones nuevas, las calificaciones y los avisos de tu grupo.') ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Notificación</th><th>Tipo</th><th>Fecha</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $n): ?>
          <tr<?= $n['leida'] ? '' : ' style="background:var(--brand-soft)"' ?>>
            <td>
              <strong><?= h($n['titulo']) ?></strong><br>
              <span class="txt-sm txt-muted"><?= h($n['mensaje']) ?></span>
            </td>
            <td><?= etiqueta_estado($n['leida'] ? 'atendido' : 'nuevo') ?></td>
            <td class="txt-sm txt-muted"><?= fecha_rel($n['creado_en']) ?></td>
            <td class="acc">
              <?php if ($n['url']): ?>
                <a class="btn btn-xs btn-ghost" href="<?= url('portal/notificaciones.php?abrir=' . (int) $n['id']) ?>">Abrir</a>
              <?php else: ?>
                <a class="btn btn-xs btn-ghost" href="<?= url('portal/notificaciones.php?abrir=' . (int) $n['id']) ?>">Marcar leída</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php pie(); ?>
