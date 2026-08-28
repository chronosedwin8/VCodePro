<?php
/**
 * Licencias del producto: emisión, cupo y puestos asignados.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

const PLANES = [
    'personal' => ['Personal', 1, 150000, 'mes'],
    'escuela'  => ['Escuela', 100, 5000000, 'año'],
    'sitio'    => ['Licencia de Sitio', 500, 20000000, 'año'],
];

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');
    $id = post_int('id');

    if ($accion === 'emitir') {
        $plan = array_key_exists(post('plan'), PLANES) ? post('plan') : 'escuela';
        $meses = $plan === 'personal' ? 1 : 12;
        $lid = insertar('licencias', [
            'colegio_id' => post_int('colegio_id') ?: null,
            'cliente_id' => post_int('cliente_id') ?: null,
            'clave'      => generar_clave_licencia($plan),
            'plan'       => $plan,
            'cupo'       => post_int('cupo') ?: PLANES[$plan][1],
            'emitida_en' => post('emitida_en') ?: date('Y-m-d'),
            'vence_en'   => post('vence_en') ?: date('Y-m-d', strtotime("+$meses months")),
            'estado'     => 'activa',
            'notas'      => post('notas') ?: null,
        ]);
        auditar('licencia_emitida', 'licencias', $lid, $plan);
        flash_ok('Licencia emitida: ' . valor('SELECT clave FROM licencias WHERE id = ?', [$lid]));
    }

    if ($accion === 'estado' && $id) {
        actualizar('licencias', ['estado' => post('valor')], 'id = :id', ['id' => $id]);
        auditar('licencia_estado', 'licencias', $id, post('valor'));
        flash_ok('Estado de la licencia actualizado.');
    }

    if ($accion === 'renovar' && $id) {
        $l = fila('SELECT * FROM licencias WHERE id = ?', [$id]);
        if ($l) {
            $meses = $l['plan'] === 'personal' ? 1 : 12;
            $base = max(time(), strtotime($l['vence_en']));
            actualizar('licencias', [
                'vence_en' => date('Y-m-d', strtotime("+$meses months", $base)),
                'estado'   => 'activa',
            ], 'id = :id', ['id' => $id]);
            auditar('licencia_renovada', 'licencias', $id);
            flash_ok('Licencia renovada.');
        }
    }

    if ($accion === 'eliminar' && $id) {
        borrar('licencias', 'id = ?', [$id]);
        flash_ok('Licencia eliminada con sus puestos.');
    }

    if ($accion === 'revocar_puesto') {
        actualizar('licencia_puestos', ['estado' => 'revocado'], 'id = :id', ['id' => post_int('puesto_id')]);
        flash_ok('Puesto revocado.');
    }
    redirigir('portal/admin/licencias.php' . (get_int('ver') ? '?ver=' . get_int('ver') : ''));
}

// Marca vencidas las licencias cuya fecha ya pasó.
q('UPDATE licencias SET estado = "vencida" WHERE estado = "activa" AND vence_en < CURDATE()');

$licencias = filas('SELECT l.*, c.nombre AS colegio,
                           CONCAT(u.nombre, " ", u.apellidos) AS cliente, u.email AS cliente_email,
                           (SELECT COUNT(*) FROM licencia_puestos p WHERE p.licencia_id = l.id AND p.estado = "activo") AS usados
                      FROM licencias l
                 LEFT JOIN colegios c ON c.id = l.colegio_id
                 LEFT JOIN usuarios u ON u.id = l.cliente_id
                  ORDER BY l.estado, l.vence_en');

$ver = get_int('ver');
$detalle = $ver ? fila('SELECT l.*, c.nombre AS colegio FROM licencias l LEFT JOIN colegios c ON c.id = l.colegio_id WHERE l.id = ?', [$ver]) : null;
$puestos = $detalle ? filas('SELECT * FROM licencia_puestos WHERE licencia_id = ? ORDER BY estado, nombre', [$ver]) : [];

$colegios = filas('SELECT id, nombre FROM colegios ORDER BY nombre');
$clientes = filas('SELECT id, nombre, apellidos, email FROM usuarios WHERE rol IN ("cliente","admin") ORDER BY apellidos');

$tot = fila('SELECT COUNT(*) AS total, SUM(estado="activa") AS activas,
                    COALESCE(SUM(CASE WHEN estado="activa" THEN cupo ELSE 0 END),0) AS cupo
               FROM licencias');
$usados = (int) valor('SELECT COUNT(*) FROM licencia_puestos WHERE estado = "activo"', [], 0);

cabecera('Licencias', [
    'titulo' => 'Licencias',
    'sub'    => 'Claves emitidas, cupo contratado y puestos en uso.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Licencias']],
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Licencias emitidas', (int) $tot['total']) ?>
  <?= metrica('Activas', (int) $tot['activas'], null, 'ok') ?>
  <?= metrica('Cupo contratado', (int) $tot['cupo'], 'puestos', 'brand') ?>
  <?= metrica('Puestos en uso', $usados, porcentaje((float) $usados, (float) ($tot['cupo'] ?: 1)) . '% del cupo') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h"><h2>Licencias</h2></div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Clave</th><th>Titular</th><th>Plan</th><th style="min-width:140px">Uso</th><th>Vigencia</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
          <tbody>
          <?php foreach ($licencias as $l): $d = dias_para($l['vence_en']); ?>
            <tr>
              <td><code class="mono copiar" data-copiar="<?= h($l['clave']) ?>"><?= h($l['clave']) ?></code></td>
              <td class="txt-sm">
                <?= h($l['colegio'] ?? '—') ?>
                <?php if ($l['cliente']): ?><br><span class="txt-muted"><?= h($l['cliente']) ?></span><?php endif; ?>
              </td>
              <td class="txt-sm"><?= h(PLANES[$l['plan']][0] ?? $l['plan']) ?></td>
              <td>
                <?= barra(porcentaje((float) $l['usados'], (float) max(1, (int) $l['cupo'])), (int) $l['usados'] >= (int) $l['cupo'] ? 'err' : '') ?>
                <span class="txt-sm txt-muted"><?= (int) $l['usados'] ?> de <?= (int) $l['cupo'] ?></span>
              </td>
              <td class="txt-sm">
                <?= fecha($l['vence_en']) ?><br>
                <span class="txt-muted"><?= $d < 0 ? 'venció hace ' . abs($d) . ' d' : 'faltan ' . $d . ' d' ?></span>
              </td>
              <td><?= etiqueta_estado($l['estado']) ?></td>
              <td class="acc">
                <form method="post" class="btn-fila">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                  <a class="btn btn-xs btn-ghost" href="<?= url('portal/admin/licencias.php?ver=' . (int) $l['id']) ?>">Puestos</a>
                  <button class="btn btn-xs btn-ghost" name="accion" value="renovar">Renovar</button>
                  <button class="btn btn-xs btn-ghost" name="accion" value="estado"
                          onclick="this.form.valor.value='<?= $l['estado'] === 'suspendida' ? 'activa' : 'suspendida' ?>'">
                    <?= $l['estado'] === 'suspendida' ? 'Reactivar' : 'Suspender' ?>
                  </button>
                  <button class="btn btn-xs btn-err" name="accion" value="eliminar" data-confirmar="¿Eliminar la licencia?">Eliminar</button>
                  <input type="hidden" name="valor" value="activa">
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($detalle): ?>
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Puestos de <?= h($detalle['clave']) ?></h2>
        <a class="txt-sm" href="<?= url('portal/admin/licencias.php') ?>">Cerrar</a>
      </div>
      <?php if (!$puestos): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Sin puestos asignados todavía.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Persona</th><th>Correo</th><th>Dispositivo</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($puestos as $p): ?>
              <tr>
                <td><?= h($p['nombre']) ?></td>
                <td class="txt-sm txt-muted"><?= h($p['email']) ?></td>
                <td class="txt-sm"><?= h($p['dispositivo'] ?? '—') ?></td>
                <td><?= etiqueta_estado($p['estado'] === 'activo' ? 'activo' : 'retirado') ?></td>
                <td class="acc">
                  <?php if ($p['estado'] === 'activo'): ?>
                    <form method="post">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="accion" value="revocar_puesto">
                      <input type="hidden" name="puesto_id" value="<?= (int) $p['id'] ?>">
                      <button class="btn btn-xs btn-ghost" data-confirmar="¿Revocar este puesto?">Revocar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="emitir">
      <div class="panel-h"><h3>Emitir licencia</h3></div>
      <div class="campo">
        <label for="plan">Plan</label>
        <select id="plan" name="plan">
          <?php foreach (PLANES as $k => $p): ?>
            <option value="<?= $k ?>"><?= h($p[0]) ?> · <?= $p[1] ?> puestos · <?= moneda((float) $p[2]) ?>/<?= $p[3] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="colegio_id">Colegio</label>
        <select id="colegio_id" name="colegio_id">
          <option value="0">Sin asignar</option>
          <?php foreach ($colegios as $c): ?><option value="<?= (int) $c['id'] ?>"><?= h($c['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="cliente_id">Contacto titular</label>
        <select id="cliente_id" name="cliente_id">
          <option value="0">Sin asignar</option>
          <?php foreach ($clientes as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= h(trim($c['nombre'] . ' ' . $c['apellidos'])) ?> · <?= h($c['email']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="cupo">Cupo</label><input type="number" id="cupo" name="cupo" min="1" max="5000" placeholder="según el plan"></div>
        <div class="campo"><label for="vence_en">Vence</label><input type="date" id="vence_en" name="vence_en" value="<?= date('Y-m-d', strtotime('+1 year')) ?>"></div>
      </div>
      <div class="campo"><label for="notas">Notas</label><textarea id="notas" name="notas" style="min-height:70px"></textarea></div>
      <button class="btn btn-block" type="submit">Emitir</button>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Formato de la clave</h3></div>
      <pre class="codigo">VCP-E27C-02S4-9HKD-4B7Q
     │
     └─ plan: P personal
        E escuela · S sitio</pre>
      <p class="txt-sm txt-muted mb-0">La clave se genera al emitir y no puede modificarse. Para cambiar el cupo, emite una licencia nueva.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
