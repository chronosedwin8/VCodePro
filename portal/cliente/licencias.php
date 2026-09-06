<?php
/**
 * Licencias del cliente y activación de clave.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/pagos_api.php';

$u = exigir_rol('cliente', 'admin');

if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'renovar') {
        $l = fila('SELECT * FROM licencias WHERE id = ? AND (cliente_id = ? OR colegio_id = ?)',
                  [post_int('licencia_id'), $u['id'], $u['colegio_id']]);
        if (!$l) {
            flash_err('Esa licencia no está en tu cuenta.');
        } else {
            $f = factura_de_renovacion($l, (int) $u['id']);
            if ($f) {
                flash_ok('Emitimos la factura ' . $f['numero'] . ' para la renovación.');
                redirigir('portal/cliente/pagar.php?factura=' . (int) $f['id']);
            }
            flash_err('No se pudo emitir la factura de renovación.');
        }
        redirigir('portal/cliente/licencias.php');
    }

    if (post('accion') === 'activar') {
        $clave = mb_strtoupper(trim(post('clave')));
        $l = fila('SELECT * FROM licencias WHERE clave = ?', [$clave]);
        if (!$l) {
            flash_err('Esa clave no existe. Verifica que la copiaste completa, con los guiones.');
        } elseif ($l['cliente_id'] && (int) $l['cliente_id'] !== $u['id']) {
            flash_err('Esa clave ya está vinculada a otra cuenta.');
        } else {
            actualizar('licencias', [
                'cliente_id' => $u['id'],
                'colegio_id' => $l['colegio_id'] ?: $u['colegio_id'],
            ], 'id = :id', ['id' => $l['id']]);
            auditar('licencia_activada', 'licencias', (int) $l['id'], $clave);
            flash_ok('Licencia vinculada a tu cuenta.');
        }
    }
    redirigir('portal/cliente/licencias.php');
}

$licencias = filas('SELECT l.*, c.nombre AS colegio,
                           (SELECT COUNT(*) FROM licencia_puestos p WHERE p.licencia_id = l.id AND p.estado = "activo") AS usados
                      FROM licencias l LEFT JOIN colegios c ON c.id = l.colegio_id
                     WHERE l.cliente_id = ? OR (l.colegio_id = ? AND l.colegio_id IS NOT NULL)
                  ORDER BY l.estado, l.vence_en', [$u['id'], $u['colegio_id']]);

cabecera('Licencias', [
    'titulo' => 'Mis licencias',
    'sub'    => 'Claves contratadas, vigencia y ocupación del cupo.',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Licencias']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <?php if (!$licencias): ?>
      <?= vacio('Sin licencias vinculadas', 'Si el colegio ya compró una licencia, activa la clave en el formulario de la derecha.') ?>
    <?php else: ?>
      <?php foreach ($licencias as $l): $d = dias_para($l['vence_en']); ?>
        <div class="panel">
          <div class="panel-h">
            <div>
              <h2><?= h(ucfirst($l['plan'])) ?> · <?= (int) $l['cupo'] ?> puestos</h2>
              <p><?= h($l['colegio'] ?? 'Sin colegio asociado') ?></p>
            </div>
            <?= etiqueta_estado($l['estado']) ?>
          </div>
          <p>
            <code class="mono copiar" data-copiar="<?= h($l['clave']) ?>"
                  style="font-size:1.2rem;letter-spacing:.12em;padding:.45rem .8rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-alt)"><?= h($l['clave']) ?></code>
          </p>
          <p class="campo-label">Ocupación del cupo</p>
          <?= barra(porcentaje((float) $l['usados'], (float) max(1, (int) $l['cupo'])), (int) $l['usados'] >= (int) $l['cupo'] ? 'err' : '') ?>
          <p class="txt-sm txt-muted"><?= (int) $l['usados'] ?> de <?= (int) $l['cupo'] ?> puestos asignados</p>
          <dl class="dl mt-2">
            <dt>Emitida</dt><dd><?= fecha($l['emitida_en']) ?></dd>
            <dt>Vence</dt><dd><?= fecha($l['vence_en']) ?> · <?= $d < 0 ? 'vencida hace ' . abs($d) . ' días' : 'faltan ' . $d . ' días' ?></dd>
            <?php if ($l['notas']): ?><dt>Notas</dt><dd><?= h($l['notas']) ?></dd><?php endif; ?>
          </dl>
          <div class="form-acc">
            <a class="btn btn-ghost btn-sm" href="<?= url('portal/cliente/puestos.php?licencia=' . (int) $l['id']) ?>">Administrar puestos</a>
            <?php if ($d < 60): ?>
              <form method="post" style="display:inline">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="renovar">
                <input type="hidden" name="licencia_id" value="<?= (int) $l['id'] ?>">
                <button class="btn btn-sm" type="submit">Renovar y pagar</button>
              </form>
              <a class="btn btn-ghost btn-sm" href="<?= url('portal/cliente/soporte.php?asunto=' . urlencode('Renovación de la licencia ' . $l['clave'])) ?>">Prefiero hablar con ventas</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="activar">
      <div class="panel-h"><h3>Activar una clave</h3></div>
      <div class="campo">
        <label for="clave">Clave de licencia</label>
        <input type="text" id="clave" name="clave" required placeholder="VCP-EXXX-XXXX-XXXX-XXXX"
               style="text-transform:uppercase;font-family:var(--mono)">
        <span class="pista">La recibe la rectoría al confirmar la compra.</span>
      </div>
      <button class="btn btn-block" type="submit">Vincular a mi cuenta</button>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Planes disponibles</h3></div>
      <table class="tabla tabla-mini">
        <tbody>
          <tr><td>Personal</td><td class="num">1 licencia</td><td class="num"><?= moneda(150000) ?>/mes</td></tr>
          <tr><td>Escuela</td><td class="num">hasta 100</td><td class="num"><?= moneda(5000000) ?>/año</td></tr>
          <tr><td>Sitio</td><td class="num">hasta 500</td><td class="num"><?= moneda(20000000) ?>/año</td></tr>
        </tbody>
      </table>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= url('precios.html') ?>">Ver la calculadora</a>
    </div>
  </aside>
</div>
<?php pie(); ?>
