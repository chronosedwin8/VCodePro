<?php
/**
 * Bitácora del ciclo de diseño del estudiante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('estudiante');

if (es_post()) {
    exigir_csrf();
    if (post('accion') === 'nueva') {
        if (post('titulo') === '') {
            flash_err('La entrada necesita un título.');
        } else {
            insertar('bitacora', [
                'estudiante_id' => $u['id'],
                'entrega_id'    => post_int('entrega_id') ?: null,
                'fase'          => in_array(post('fase'), array_keys(FASES_CICLO), true) ? post('fase') : 'general',
                'titulo'        => post('titulo'),
                'contenido'     => post_rico('contenido'),
                'minutos'       => post_int('minutos'),
            ]);
            revisar_insignias($u['id']);
            flash_ok('Entrada registrada.');
        }
    } elseif (post('accion') === 'borrar') {
        borrar('bitacora', 'id = ? AND estudiante_id = ?', [post_int('id'), $u['id']]);
        flash_ok('Entrada eliminada.');
    }
    redirigir('portal/estudiante/bitacora.php');
}

if (get('exportar') === 'csv') {
    $filas = filas('SELECT b.creado_en, b.fase, b.titulo, b.contenido, b.minutos, ac.codigo, ac.titulo AS actividad
                      FROM bitacora b
                 LEFT JOIN entregas e ON e.id = b.entrega_id
                 LEFT JOIN asignaciones a ON a.id = e.asignacion_id
                 LEFT JOIN actividades ac ON ac.id = a.actividad_id
                     WHERE b.estudiante_id = ? ORDER BY b.id', [$u['id']]);
    descargar_csv('bitacora-' . slug(nombre_completo($u)),
        ['Fecha', 'Fase', 'Título', 'Detalle', 'Minutos', 'Código', 'Actividad'],
        array_map(fn($f) => array_values($f), $filas));
}

$faseF = get('fase');
$where = ['b.estudiante_id = ?']; $params = [$u['id']];
if (in_array($faseF, array_keys(FASES_CICLO), true)) { $where[] = 'b.fase = ?'; $params[] = $faseF; }

$entradas = filas('SELECT b.*, ac.codigo, ac.titulo AS actividad
                     FROM bitacora b
                LEFT JOIN entregas e ON e.id = b.entrega_id
                LEFT JOIN asignaciones a ON a.id = e.asignacion_id
                LEFT JOIN actividades ac ON ac.id = a.actividad_id
                    WHERE ' . implode(' AND ', $where) . '
                 ORDER BY b.id DESC LIMIT 200', $params);

$abiertas = filas('SELECT e.id, ac.codigo, ac.titulo
                     FROM entregas e
                     JOIN asignaciones a ON a.id = e.asignacion_id
                     JOIN actividades ac ON ac.id = a.actividad_id
                    WHERE e.estudiante_id = ? AND e.estado <> "revisada"
                 ORDER BY a.fecha_entrega', [$u['id']]);

$tot = fila('SELECT COUNT(*) AS n, COALESCE(SUM(minutos),0) AS min FROM bitacora WHERE estudiante_id = ?', [$u['id']]);

cabecera('Bitácora', [
    'titulo' => 'Mi bitácora',
    'sub'    => 'El registro de tu proceso. Es la evidencia que sustenta los criterios C y D.',
    'migas'  => [['Panel', 'portal/estudiante/index.php'], ['Bitácora']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/estudiante/bitacora.php?exportar=csv') . '">Exportar CSV</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Entradas registradas', (int) $tot['n']) ?>
  <?= metrica('Tiempo documentado', round(((int) $tot['min']) / 60, 1) . ' h', null, 'brand') ?>
  <?= metrica('Entradas este mes', (int) valor('SELECT COUNT(*) FROM bitacora WHERE estudiante_id = ? AND MONTH(creado_en) = MONTH(CURDATE())', [$u['id']], 0)) ?>
  <?= metrica('Actividades con registro', (int) valor('SELECT COUNT(DISTINCT entrega_id) FROM bitacora WHERE estudiante_id = ? AND entrega_id IS NOT NULL', [$u['id']], 0)) ?>
</div>

<div class="rejilla rej-lat">
  <div>
    <form class="acciones-barra" method="get">
      <select name="fase">
        <option value="">Todas las fases</option>
        <?php foreach (FASES_CICLO as $k => $v): ?>
          <option value="<?= $k ?>" <?= $faseF === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm" type="submit">Filtrar</button>
    </form>

    <div class="panel">
      <?php if (!$entradas): ?>
        <?= vacio('Tu bitácora está vacía', 'Registra al final de cada sesión qué hiciste, qué falló y qué decidiste. Toma dos minutos y vale por toda la evidencia del criterio C.') ?>
      <?php else: ?>
        <ul class="linea">
          <?php foreach ($entradas as $b): ?>
            <li>
              <h4><?= h($b['titulo']) ?></h4>
              <time>
                <?= h(nombre_fase($b['fase'])) ?> · <?= fecha($b['creado_en'], true) ?> · <?= (int) $b['minutos'] ?> min
                <?php if ($b['codigo']): ?> · <span class="act-cod"><?= h($b['codigo']) ?></span><?php endif; ?>
              </time>
              <?php if ($b['contenido']): ?><?= bloque_rico($b['contenido']) ?><?php endif; ?>
              <form method="post" style="margin-top:.3rem">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="borrar">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-xs btn-ghost" data-confirmar="¿Eliminar esta entrada de la bitácora?">Eliminar</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="nueva">
      <div class="panel-h"><h3>Nueva entrada</h3></div>
      <div class="campo">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" required placeholder="Ej.: Probé el prototipo con dos usuarios">
      </div>
      <div class="campo">
        <label for="entrega_id">Actividad</label>
        <select id="entrega_id" name="entrega_id">
          <option value="0">Sin actividad asociada</option>
          <?php foreach ($abiertas as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= h($a['codigo'] . ' · ' . corte($a['titulo'], 40)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="fase">Fase</label>
          <select id="fase" name="fase">
            <?php foreach (FASES_CICLO as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="minutos">Minutos</label>
          <input type="number" id="minutos" name="minutos" min="0" max="600" value="45">
        </div>
      </div>
      <div class="campo">
        <label for="contenido">Detalle</label>
        <textarea id="contenido" name="contenido" data-rico placeholder="Qué hiciste, qué no funcionó, qué decidiste y por qué."></textarea>
      </div>
      <button class="btn btn-block" type="submit">Registrar entrada</button>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Cómo escribir una buena entrada</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li>Escribe el mismo día, no al final del proyecto.</li>
        <li>Registra los errores: son la evidencia más valiosa.</li>
        <li>Explica la decisión, no solo la acción.</li>
        <li>Cierra siempre con el siguiente paso.</li>
      </ul>
    </div>
  </aside>
</div>
<?php pie(); ?>
