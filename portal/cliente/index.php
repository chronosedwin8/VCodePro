<?php
/**
 * Panel del cliente institucional.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('cliente', 'admin');

$licencias = filas('SELECT l.*, c.nombre AS colegio,
                           (SELECT COUNT(*) FROM licencia_puestos p WHERE p.licencia_id = l.id AND p.estado = "activo") AS usados
                      FROM licencias l LEFT JOIN colegios c ON c.id = l.colegio_id
                     WHERE l.cliente_id = ? OR (l.colegio_id = ? AND l.colegio_id IS NOT NULL)
                  ORDER BY l.estado, l.vence_en', [$u['id'], $u['colegio_id']]);

$facturas = filas('SELECT * FROM facturas WHERE cliente_id = ? ORDER BY emitida_en DESC LIMIT 6', [$u['id']]);
$tickets  = filas('SELECT * FROM tickets WHERE cliente_id = ? ORDER BY creado_en DESC LIMIT 5', [$u['id']]);

$cupo   = array_sum(array_map(fn($l) => $l['estado'] === 'activa' ? (int) $l['cupo'] : 0, $licencias));
$usados = array_sum(array_map(fn($l) => $l['estado'] === 'activa' ? (int) $l['usados'] : 0, $licencias));
$porPagar = array_sum(array_map(fn($f) => in_array($f['estado'], ['pendiente', 'vencida'], true) ? (float) $f['monto'] : 0, $facturas));

$academico = $u['colegio_id'] ? fila('SELECT
        (SELECT COUNT(*) FROM usuarios WHERE colegio_id = ? AND rol = "estudiante") AS estudiantes,
        (SELECT COUNT(*) FROM usuarios WHERE colegio_id = ? AND rol = "docente")    AS docentes,
        (SELECT COUNT(*) FROM grupos WHERE colegio_id = ? AND estado = "activo")    AS grupos',
    [$u['colegio_id'], $u['colegio_id'], $u['colegio_id']]) : [];

cabecera('Panel del cliente', [
    'titulo' => 'Hola, ' . $u['nombre'],
    'sub'    => ($u['colegio_nombre'] ?? 'Tu institución') . ' · estado de tus licencias y servicios.',
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/cliente/soporte.php') . '">Abrir un ticket</a>'
                . '<a class="btn" href="' . url('portal/cliente/descargas.php') . '">Descargar VCodePro</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Puestos contratados', $cupo, 'en licencias activas', 'brand') ?>
  <?= metrica('Puestos asignados', $usados, porcentaje((float) $usados, (float) max(1, $cupo)) . '% del cupo') ?>
  <?= metrica('Estudiantes registrados', (int) ($academico['estudiantes'] ?? 0), (int) ($academico['docentes'] ?? 0) . ' docentes') ?>
  <?= metrica('Saldo por pagar', moneda($porPagar), null, $porPagar > 0 ? 'warn' : 'ok') ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Mis licencias</h2>
        <a class="txt-sm" href="<?= url('portal/cliente/licencias.php') ?>">Ver detalle</a>
      </div>
      <?php if (!$licencias): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">No hay licencias asociadas a tu cuenta. Escribe a <?= h(ajuste('contacto_soporte', 'soporte@vcodepro.de')) ?>.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Clave</th><th>Plan</th><th style="min-width:150px">Uso del cupo</th><th>Vence</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($licencias as $l): $d = dias_para($l['vence_en']); ?>
              <tr>
                <td><code class="mono copiar" data-copiar="<?= h($l['clave']) ?>"><?= h($l['clave']) ?></code></td>
                <td class="txt-sm"><?= h(ucfirst($l['plan'])) ?></td>
                <td>
                  <?= barra(porcentaje((float) $l['usados'], (float) max(1, (int) $l['cupo'])), (int) $l['usados'] >= (int) $l['cupo'] ? 'err' : '') ?>
                  <span class="txt-sm txt-muted"><?= (int) $l['usados'] ?> de <?= (int) $l['cupo'] ?></span>
                </td>
                <td class="txt-sm"><?= fecha($l['vence_en']) ?><br><span class="txt-muted"><?= $d < 0 ? 'vencida' : 'faltan ' . $d . ' d' ?></span></td>
                <td><?= etiqueta_estado($l['estado']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Facturas recientes</h2>
        <a class="txt-sm" href="<?= url('portal/cliente/facturas.php') ?>">Ver todas</a>
      </div>
      <?php if (!$facturas): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Sin facturas registradas.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Número</th><th>Concepto</th><th class="num">Monto</th><th>Vence</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($facturas as $f): ?>
              <tr>
                <td class="mono txt-sm"><?= h($f['numero']) ?></td>
                <td class="txt-sm"><?= h($f['concepto']) ?></td>
                <td class="num"><?= moneda((float) $f['monto'], $f['moneda']) ?></td>
                <td class="txt-sm"><?= fecha($f['vence_en']) ?></td>
                <td><?= etiqueta_estado($f['estado']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Uso académico</h3></div>
      <dl class="dl">
        <dt>Grupos activos</dt><dd><?= (int) ($academico['grupos'] ?? 0) ?></dd>
        <dt>Docentes</dt><dd><?= (int) ($academico['docentes'] ?? 0) ?></dd>
        <dt>Estudiantes</dt><dd><?= (int) ($academico['estudiantes'] ?? 0) ?></dd>
      </dl>
      <p class="txt-sm txt-muted mb-0">El detalle académico por curso lo administra el equipo docente desde su propio panel.</p>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Soporte</h3><a class="txt-sm" href="<?= url('portal/cliente/soporte.php') ?>">Abrir</a></div>
      <?php if (!$tickets): ?>
        <p class="txt-muted mb-0">No tienes solicitudes abiertas.</p>
      <?php else: ?>
        <ul class="linea">
          <?php foreach ($tickets as $t): ?>
            <li>
              <h4><a href="<?= url('portal/cliente/soporte.php?ver=' . (int) $t['id']) ?>"><?= h(corte($t['asunto'], 44)) ?></a></h4>
              <time><?= h(ucfirst($t['categoria'])) ?> · <?= fecha_rel($t['creado_en']) ?></time>
              <p><?= etiqueta_estado($t['estado']) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php pie(); ?>
