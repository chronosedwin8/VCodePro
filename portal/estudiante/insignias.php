<?php
/**
 * Insignias y puntos del estudiante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');
revisar_insignias($u['id']);

$todas = filas('SELECT i.*, ui.obtenida_en
                  FROM insignias i
             LEFT JOIN usuario_insignias ui ON ui.insignia_id = i.id AND ui.usuario_id = ?
              ORDER BY ui.obtenida_en IS NULL, i.puntos', [$u['id']]);

$obtenidas = array_filter($todas, fn($i) => $i['obtenida_en'] !== null);
$puntos = array_sum(array_map(fn($i) => (int) $i['puntos'], $obtenidas));
$maxPuntos = array_sum(array_map(fn($i) => (int) $i['puntos'], $todas));

cabecera('Insignias', [
    'titulo' => 'Mis insignias',
    'sub'    => 'Reconocimientos por hábitos de trabajo, no solo por resultados.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Insignias']],
]);
?>
<div class="rejilla rej-3 mb-2">
  <?= metrica('Insignias obtenidas', count($obtenidas) . ' de ' . count($todas)) ?>
  <?= metrica('Puntos', $puntos . ' de ' . $maxPuntos, null, 'brand') ?>
  <?= metrica('Avance', porcentaje((float) $puntos, (float) $maxPuntos) . '%') ?>
</div>

<div class="panel">
  <div class="panel-h"><h2>Colección</h2><p>Las grises todavía no las has conseguido.</p></div>
  <div class="insignias">
    <?php foreach ($todas as $i): ?>
      <div class="ins<?= $i['obtenida_en'] ? '' : ' ins-off' ?>" title="<?= h($i['descripcion']) ?>">
        <div class="cara"><?= h($i['icono']) ?></div>
        <b><?= h($i['nombre']) ?></b>
        <span><?= (int) $i['puntos'] ?> pts</span>
        <span class="txt-sm txt-muted"><?= $i['obtenida_en'] ? fecha($i['obtenida_en']) : 'pendiente' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-h"><h2>Cómo se consiguen</h2></div>
  <div class="tabla-caja">
    <table class="tabla">
      <thead><tr><th>Insignia</th><th>Requisito</th><th class="num">Puntos</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($todas as $i): ?>
        <tr>
          <td><strong><?= h($i['nombre']) ?></strong></td>
          <td class="txt-sm txt-muted"><?= h($i['descripcion']) ?></td>
          <td class="num"><?= (int) $i['puntos'] ?></td>
          <td><?= $i['obtenida_en'] ? '<span class="chip chip-verde">Obtenida</span>' : '<span class="chip chip-gris">Pendiente</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pie(); ?>
