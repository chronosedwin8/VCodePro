<?php
/**
 * Reparto de puestos de una licencia.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('cliente', 'admin');

/** Licencias que el cliente puede administrar. */
$misLicencias = filas('SELECT l.*, (SELECT COUNT(*) FROM licencia_puestos p WHERE p.licencia_id = l.id AND p.estado = "activo") AS usados
                         FROM licencias l
                        WHERE l.cliente_id = ? OR (l.colegio_id = ? AND l.colegio_id IS NOT NULL)
                     ORDER BY l.estado, l.vence_en', [$u['id'], $u['colegio_id']]);

$ids = array_map(fn($l) => (int) $l['id'], $misLicencias);
$licenciaId = get_int('licencia') ?: ($ids[0] ?? 0);
if ($licenciaId && !in_array($licenciaId, $ids, true)) $licenciaId = $ids[0] ?? 0;

if (es_post()) {
    exigir_csrf();
    $lid = post_int('licencia_id');
    if (!in_array($lid, $ids, true)) { flash_err('Licencia no disponible.'); redirigir('portal/cliente/puestos.php'); }
    $lic = fila('SELECT * FROM licencias WHERE id = ?', [$lid]);
    $usados = (int) valor('SELECT COUNT(*) FROM licencia_puestos WHERE licencia_id = ? AND estado = "activo"', [$lid], 0);
    $accion = post('accion');

    if ($accion === 'asignar') {
        $email = mb_strtolower(post('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_err('El correo no es válido.');
        } elseif ($usados >= (int) $lic['cupo']) {
            flash_err('El cupo de la licencia está completo. Revoca un puesto o amplía el plan.');
        } elseif (valor('SELECT id FROM licencia_puestos WHERE licencia_id = ? AND email = ? AND estado = "activo"', [$lid, $email])) {
            flash_err('Ese correo ya tiene un puesto activo en esta licencia.');
        } else {
            insertar('licencia_puestos', [
                'licencia_id' => $lid,
                'nombre'      => post('nombre') ?: $email,
                'email'       => $email,
                'dispositivo' => post('dispositivo') ?: null,
            ]);
            auditar('puesto_asignado', 'licencias', $lid, $email);
            flash_ok('Puesto asignado.');
        }
    }

    if ($accion === 'importar') {
        $creados = 0; $rechazados = 0;
        foreach (preg_split('/\R/', post('lista')) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;
            $partes = array_map('trim', preg_split('/[;,\t]/', $linea) ?: []);
            $email = mb_strtolower(end($partes));
            $nombre = count($partes) > 1 ? $partes[0] : $email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $rechazados++; continue; }
            if ($usados + $creados >= (int) $lic['cupo']) { $rechazados++; continue; }
            if (valor('SELECT id FROM licencia_puestos WHERE licencia_id = ? AND email = ? AND estado = "activo"', [$lid, $email])) { $rechazados++; continue; }
            insertar('licencia_puestos', ['licencia_id' => $lid, 'nombre' => $nombre, 'email' => $email]);
            $creados++;
        }
        flash_ok("Se asignaron $creados puesto(s)." . ($rechazados ? " $rechazados línea(s) quedaron fuera por cupo o formato." : ''));
    }

    if ($accion === 'revocar') {
        actualizar('licencia_puestos', ['estado' => 'revocado'], 'id = :id AND licencia_id = :l', ['id' => post_int('puesto_id'), 'l' => $lid]);
        flash_ok('Puesto revocado: el cupo queda libre.');
    }

    if ($accion === 'reactivar') {
        if ($usados >= (int) $lic['cupo']) {
            flash_err('No hay cupo disponible para reactivar el puesto.');
        } else {
            actualizar('licencia_puestos', ['estado' => 'activo'], 'id = :id AND licencia_id = :l', ['id' => post_int('puesto_id'), 'l' => $lid]);
            flash_ok('Puesto reactivado.');
        }
    }
    redirigir('portal/cliente/puestos.php?licencia=' . $lid);
}

$lic = $licenciaId ? fila('SELECT * FROM licencias WHERE id = ?', [$licenciaId]) : null;
$puestos = $lic ? filas('SELECT * FROM licencia_puestos WHERE licencia_id = ? ORDER BY estado, nombre', [$licenciaId]) : [];
$usados = count(array_filter($puestos, fn($p) => $p['estado'] === 'activo'));

if ($lic && get('exportar') === 'csv') {
    descargar_csv('puestos-' . slug($lic['clave']), ['Nombre', 'Correo', 'Dispositivo', 'Estado', 'Asignado'],
        array_map(fn($p) => [$p['nombre'], $p['email'], $p['dispositivo'], $p['estado'], $p['asignado_en']], $puestos));
}

cabecera('Puestos', [
    'titulo' => 'Reparto de puestos',
    'sub'    => $lic ? $lic['clave'] . ' · ' . $usados . ' de ' . (int) $lic['cupo'] . ' puestos en uso' : 'Sin licencias disponibles.',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Puestos']],
    'acciones' => $lic ? '<a class="btn btn-ghost" href="' . url('portal/cliente/puestos.php?licencia=' . $licenciaId . '&exportar=csv') . '">Exportar CSV</a>' : '',
]);
?>
<?php if (!$lic): ?>
  <?= vacio('No hay licencias para administrar', 'Activa primero una clave de licencia.',
        '<a class="btn" href="' . url('portal/cliente/licencias.php') . '">Ir a licencias</a>') ?>
<?php else: ?>

<form class="acciones-barra" method="get">
  <select name="licencia" onchange="this.form.submit()">
    <?php foreach ($misLicencias as $l): ?>
      <option value="<?= (int) $l['id'] ?>" <?= $licenciaId === (int) $l['id'] ? 'selected' : '' ?>>
        <?= h($l['clave']) ?> · <?= h(ucfirst($l['plan'])) ?> · <?= (int) $l['usados'] ?>/<?= (int) $l['cupo'] ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="search" data-filtra="#tabla-puestos" placeholder="Buscar por nombre o correo" class="crece">
</form>

<div class="panel">
  <div class="panel-h">
    <h2>Ocupación</h2>
    <p><?= $usados ?> de <?= (int) $lic['cupo'] ?> · <?= (int) $lic['cupo'] - $usados ?> disponibles</p>
  </div>
  <?= barra(porcentaje((float) $usados, (float) max(1, (int) $lic['cupo'])), $usados >= (int) $lic['cupo'] ? 'err' : '') ?>
</div>

<div class="rejilla rej-lat">
  <div class="panel panel-plano">
    <div class="panel-h"><h2>Puestos asignados</h2></div>
    <?php if (!$puestos): ?>
      <div style="padding:20px"><p class="txt-muted mb-0">Todavía no has asignado puestos.</p></div>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla" id="tabla-puestos">
          <thead><tr><th>Persona</th><th>Correo</th><th>Dispositivo</th><th>Asignado</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
          <tbody>
          <?php foreach ($puestos as $p): ?>
            <tr<?= $p['estado'] !== 'activo' ? ' style="opacity:.55"' : '' ?>>
              <td><strong><?= h($p['nombre']) ?></strong></td>
              <td class="txt-sm txt-muted"><?= h($p['email']) ?></td>
              <td class="txt-sm"><?= h($p['dispositivo'] ?? '—') ?></td>
              <td class="txt-sm txt-muted"><?= fecha($p['asignado_en']) ?></td>
              <td><?= etiqueta_estado($p['estado'] === 'activo' ? 'activo' : 'retirado') ?></td>
              <td class="acc">
                <form method="post">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="licencia_id" value="<?= $licenciaId ?>">
                  <input type="hidden" name="puesto_id" value="<?= (int) $p['id'] ?>">
                  <?php if ($p['estado'] === 'activo'): ?>
                    <button class="btn btn-xs btn-ghost" name="accion" value="revocar" data-confirmar="¿Revocar el puesto y liberar el cupo?">Revocar</button>
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
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="asignar">
      <input type="hidden" name="licencia_id" value="<?= $licenciaId ?>">
      <div class="panel-h"><h3>Asignar un puesto</h3></div>
      <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre"></div>
      <div class="campo"><label for="email">Correo</label><input type="email" id="email" name="email" required></div>
      <div class="campo"><label for="dispositivo">Dispositivo o sala</label><input type="text" id="dispositivo" name="dispositivo" placeholder="Sala de cómputo 2"></div>
      <button class="btn btn-block" type="submit">Asignar</button>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="importar">
      <input type="hidden" name="licencia_id" value="<?= $licenciaId ?>">
      <div class="panel-h"><h3>Asignación masiva</h3></div>
      <div class="campo">
        <label for="lista">Lista de correos</label>
        <textarea id="lista" name="lista" style="min-height:130px;font-family:var(--mono);font-size:.85rem"
                  placeholder="Laura Betancur;laura.betancur@colegio.edu.co
oscar.mendieta@colegio.edu.co"></textarea>
        <span class="pista">Una línea por persona. Puede ser solo el correo o nombre y correo separados por punto y coma.</span>
      </div>
      <button class="btn btn-block btn-ghost" type="submit">Asignar la lista</button>
    </form>
  </aside>
</div>
<?php endif; ?>
<?php pie(); ?>
