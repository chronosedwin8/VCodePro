<?php
/**
 * Precios de los planes: lo que muestra la web y lo que cobra Mercado Pago.
 *
 * Una sola fuente (plan_catalogo). Al guardar, la portada y la página de
 * precios los muestran en unos minutos y las compras y renovaciones nuevas se
 * cobran con estos valores de inmediato.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pagos_api.php';

$u = exigir_rol('admin');
$errores = [];
$enviado = null;

if (es_post()) {
    exigir_csrf();

    if (post('accion') === 'fabrica') {
        plan_guardar(PLANES_FABRICA);
        flash_ok('Se restauraron los precios de fábrica.');
        redirigir('portal/admin/precios.php');
    }

    $enviado = is_array($_POST['planes'] ?? null) ? $_POST['planes'] : [];
    [$ok, $errores] = plan_guardar($enviado);
    if ($ok) {
        flash_ok('Precios guardados. Las compras nuevas ya se cobran así; la web los muestra en menos de cinco minutos.');
        redirigir('portal/admin/precios.php');
    }
    flash_err('Hay datos por corregir. No se guardó ningún cambio.');
}

$catalogo = plan_catalogo();
// Si hubo errores, se vuelve a mostrar lo que el administrador escribió.
$valores = $catalogo;
if ($enviado) {
    foreach ($valores as $k => $p) {
        if (is_array($enviado[$k] ?? null)) $valores[$k] = array_merge($p, $enviado[$k]);
    }
}

$pendientes = [];
foreach (filas('SELECT l.plan, COUNT(*) AS n, SUM(f.monto) AS total
                  FROM facturas f JOIN licencias l ON l.id = f.licencia_id
                 WHERE f.estado IN ("pendiente","vencida") GROUP BY l.plan') as $r) {
    $pendientes[$r['plan']] = $r;
}
$historial = filas('SELECT a.detalle, a.creado_en, u.email
                      FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
                     WHERE a.accion = "precio_cambiado" ORDER BY a.id DESC LIMIT 10');

cabecera('Precios', [
    'titulo'   => 'Precios de los planes',
    'sub'      => 'Lo que muestra la web y lo que se cobra en Mercado Pago salen de aquí.',
    'migas'    => [['Panel', 'portal/admin/index.php'], ['Precios']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('precios.html') . '" target="_blank" rel="noopener">Ver la página de precios</a>',
]);
?>
<form method="post">
  <?= csrf_campo() ?>
  <div class="rejilla rej-3">
    <?php foreach (PLANES_FABRICA as $clave => $fabrica):
        $v = $valores[$clave];
        $e = $errores[$clave] ?? [];
        $vigente = $catalogo[$clave];
    ?>
      <fieldset class="panel" style="margin:0">
        <div class="panel-h">
          <h2><?= h($vigente['nombre']) ?></h2>
          <span class="chip chip-gris"><?= h($clave) ?></span>
        </div>

        <div class="campo">
          <label for="n-<?= $clave ?>">Nombre visible</label>
          <input type="text" id="n-<?= $clave ?>" name="planes[<?= $clave ?>][nombre]" value="<?= h((string) $v['nombre']) ?>" maxlength="40" required>
          <?php if (isset($e['nombre'])): ?><span class="pista es-error"><?= h($e['nombre']) ?></span><?php endif; ?>
        </div>

        <div class="campo">
          <label for="p-<?= $clave ?>"><?= $clave === 'personal' ? 'Precio por licencia al mes' : 'Precio' ?> (COP, sin puntos)</label>
          <input type="number" id="p-<?= $clave ?>" name="planes[<?= $clave ?>][precio]" value="<?= h((string) $v['precio']) ?>"
                 min="<?= PLAN_PRECIO_MIN ?>" max="<?= PLAN_PRECIO_MAX ?>" step="1" required data-precio="<?= $clave ?>">
          <?php if (isset($e['precio'])): ?><span class="pista es-error"><?= h($e['precio']) ?></span><?php endif; ?>
        </div>

        <?php if ($clave === 'personal'): ?>
          <p class="txt-sm txt-muted">
            Se vende por licencia, de 1 a <?= PLAN_PERSONAL_MAX ?>. Quien compra elige pagar cada mes o
            por año anticipado: 12 meses por el precio de <?= PLAN_PERSONAL_MESES_ANUAL ?>.
          </p>
        <?php else: ?>
        <div class="campo-fila">
          <div class="campo">
            <label for="c-<?= $clave ?>">Cupo</label>
            <input type="number" id="c-<?= $clave ?>" name="planes[<?= $clave ?>][cupo]" value="<?= h((string) $v['cupo']) ?>"
                   min="1" max="<?= PLAN_CUPO_MAX ?>" step="1" required>
            <?php if (isset($e['cupo'])): ?><span class="pista es-error"><?= h($e['cupo']) ?></span><?php endif; ?>
          </div>
          <div class="campo">
            <label for="m-<?= $clave ?>">Se cobra</label>
            <select id="m-<?= $clave ?>" name="planes[<?= $clave ?>][meses]">
              <option value="1"  <?= (int) $v['meses'] === 1 ? 'selected' : '' ?>>Cada mes</option>
              <option value="12" <?= (int) $v['meses'] === 12 ? 'selected' : '' ?>>Cada año</option>
            </select>
          </div>
        </div>
        <?php endif; ?>

        <div class="ia-caja">
          <h4>Así se ve hoy en la web</h4>
          <p class="mb-0" style="font-size:1.35rem;font-weight:700;color:var(--text-strong)">
            <?= h(plan_pesos($vigente['precio'])) ?> <span class="txt-sm txt-muted" style="font-weight:400">COP / <?= h(plan_periodo($vigente)) ?></span>
          </p>
          <p class="txt-sm txt-muted mb-0">
            <?= h(plan_puestos($vigente['cupo'])) ?> · <?= h(plan_pesos(plan_por_licencia_mes($vigente))) ?> por licencia al mes
          </p>
        </div>

        <p class="txt-sm txt-muted mt-2 mb-0">
          De fábrica: <?= h(plan_pesos($fabrica['precio'])) ?> / <?= $fabrica['meses'] === 1 ? 'mes' : 'año' ?> · <?= (int) $fabrica['cupo'] ?> puesto(s).
          <?php if (!empty($pendientes[$clave])): ?>
            <br><strong><?= (int) $pendientes[$clave]['n'] ?> factura(s) pendiente(s)</strong> de este plan.
          <?php endif; ?>
        </p>
      </fieldset>
    <?php endforeach; ?>
  </div>

  <div class="form-acc mt-2">
    <button class="btn btn-lg" type="submit" data-confirmar="Los precios nuevos se cobran desde este momento y se publican en la web. ¿Guardar?">Guardar precios</button>
    <button class="btn btn-ghost" name="accion" value="fabrica" formnovalidate data-confirmar="¿Volver a los precios de fábrica?">Restaurar los de fábrica</button>
  </div>
</form>

<div class="rejilla rej-lat mt-2">
  <div class="panel">
    <div class="panel-h"><h3>Qué pasa al cambiar un precio</h3></div>
    <ul class="txt-sm" style="padding-left:1.1rem">
      <li><strong>Compras y renovaciones nuevas</strong> se cobran con el precio nuevo desde que guardas.</li>
      <li><strong>Una factura pendiente sin ningún intento de pago</strong> se pone al día con el precio nuevo la próxima vez que el cliente la abra desde la compra o la renovación.</li>
      <li><strong>Si el cliente ya empezó a pagar</strong>, se respeta el valor con el que empezó: Mercado Pago cobra exactamente el monto de la factura y el portal comprueba que coincida antes de activar nada.</li>
      <li><strong>Las licencias ya pagadas</strong> no cambian. Si subes el cupo de un plan, las licencias de ese plan lo reciben al renovar.</li>
      <li><strong>La portada y la página de precios</strong> muestran el cambio en menos de cinco minutos.</li>
      <li>En el extracto de la tarjeta del cliente figura <strong><?= h(MP_DESCRIPTOR) ?></strong>, sea cual sea el precio.</li>
    </ul>
  </div>

  <aside class="panel">
    <div class="panel-h"><h3>Últimos cambios</h3></div>
    <?php if (!$historial): ?>
      <p class="txt-sm txt-muted mb-0">Todavía no se ha cambiado ningún precio.</p>
    <?php else: ?>
      <ul class="txt-sm" style="list-style:none;padding:0;margin:0;display:grid;gap:10px">
        <?php foreach ($historial as $hh): ?>
          <li><span class="txt-muted"><?= fecha($hh['creado_en'], true) ?> · <?= h($hh['email'] ?? 'sistema') ?></span><br><?= h($hh['detalle']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</div>
<?php pie(); ?>
