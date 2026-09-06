<?php
/**
 * Portafolio del estudiante: trayectoria de trabajos entregados.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');

$piezas = filas('SELECT e.id, e.texto, e.url_repo, e.archivo, e.archivo_nombre, e.entregado_en,
                        e.nota_letra, e.estado, e.progreso,
                        ac.codigo, ac.titulo, ac.resumen, ac.lenguaje, ac.contexto_global,
                        n.grado, n.nombre AS nivel, n.color, n.orden AS nivel_orden
                   FROM entregas e
                   JOIN asignaciones a ON a.id = e.asignacion_id
                   JOIN actividades ac ON ac.id = a.actividad_id
                   JOIN niveles n ON n.id = ac.nivel_id
                  WHERE e.estudiante_id = ? AND e.estado IN ("entregada","revisada","rehacer")
               ORDER BY n.orden, e.entregado_en', [$u['id']]);

$porNivel = [];
foreach ($piezas as $p) $porNivel[$p['grado'] . ' · ' . $p['nivel']][] = $p;

$horas = round(((int) valor('SELECT COALESCE(SUM(minutos),0) FROM bitacora WHERE estudiante_id = ?', [$u['id']], 0)) / 60, 1);

cabecera('Portafolio', [
    'titulo' => 'Mi portafolio',
    'sub'    => 'La trayectoria de tu trabajo, agrupada por nivel. Sirve para la sustentación y para el archivo del colegio.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Portafolio']],
    'acciones' => '<button class="btn btn-ghost no-print" onclick="window.print()">Imprimir o guardar en PDF</button>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Trabajos en el portafolio', count($piezas)) ?>
  <?= metrica('Niveles cursados', count($porNivel)) ?>
  <?= metrica('Horas documentadas', $horas . ' h', 'según tu bitácora', 'brand') ?>
  <?= metrica('Insignias', (int) valor('SELECT COUNT(*) FROM usuario_insignias WHERE usuario_id = ?', [$u['id']], 0)) ?>
</div>

<?php if (!$piezas): ?>
  <?= vacio('Tu portafolio está vacío', 'Cada actividad que entregues se sumará automáticamente a esta página.') ?>
<?php else: ?>
  <?php foreach ($porNivel as $nivel => $items): ?>
    <div class="panel">
      <div class="panel-h">
        <h2><?= h($nivel) ?></h2>
        <p><?= count($items) ?> trabajo(s)</p>
      </div>
      <div class="rejilla rej-2">
        <?php foreach ($items as $p): ?>
          <article class="act-tarjeta">
            <div class="act-meta">
              <span class="act-cod"><?= h($p['codigo']) ?></span>
              <?= etiqueta_estado($p['estado']) ?>
              <?php if ($p['nota_letra']): ?><span class="chip chip-verde">Nota <?= h($p['nota_letra']) ?></span><?php endif; ?>
            </div>
            <h3><a href="<?= url('portal/estudiante/actividad.php?e=' . (int) $p['id']) ?>"><?= h($p['titulo']) ?></a></h3>
            <p><?= h(corte(rico_plano($p['texto']) ?: $p['resumen'], 200)) ?></p>
            <div class="act-meta">
              <span class="chip chip-gris"><?= h($p['lenguaje']) ?></span>
              <span class="chip chip-azul"><?= h($p['contexto_global']) ?></span>
            </div>
            <div class="act-meta txt-sm txt-muted">
              Entregado el <?= fecha($p['entregado_en']) ?>
              <?php if ($p['url_repo']): ?> · <a href="<?= h($p['url_repo']) ?>" target="_blank" rel="noopener">producto</a><?php endif; ?>
              <?php if ($p['archivo']): ?> · <a href="<?= URL_SUBIDAS . '/' . h($p['archivo']) ?>" target="_blank" rel="noopener">archivo</a><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php pie(); ?>
