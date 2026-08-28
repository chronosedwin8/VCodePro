<?php
/**
 * Estudiantes de todos los grupos del docente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$mio = es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id'];

$grupoF = get_int('grupo');
$buscar = get('q');

$where = [$mio, 'ge.estado = "activo"'];
$params = [];
if ($grupoF) { $where[] = 'g.id = ?'; $params[] = $grupoF; }
if ($buscar !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}

$estudiantes = filas('SELECT u.id, u.nombre, u.apellidos, u.email, u.ultimo_acceso, u.estado,
                             GROUP_CONCAT(DISTINCT g.nombre ORDER BY g.nombre SEPARATOR ", ") AS grupos,
                             COUNT(DISTINCT e.id) AS entregas,
                             SUM(e.estado = "entregada") AS por_revisar,
                             SUM(e.estado = "revisada")  AS calificadas,
                             SUM(e.estado = "rehacer")   AS rehacer,
                             ROUND(AVG(e.progreso))      AS avance
                        FROM grupo_estudiantes ge
                        JOIN grupos g ON g.id = ge.grupo_id
                        JOIN usuarios u ON u.id = ge.estudiante_id
                   LEFT JOIN asignaciones a ON a.grupo_id = g.id
                   LEFT JOIN entregas e ON e.asignacion_id = a.id AND e.estudiante_id = u.id
                       WHERE ' . implode(' AND ', $where) . '
                    GROUP BY u.id, u.nombre, u.apellidos, u.email, u.ultimo_acceso, u.estado
                    ORDER BY u.apellidos, u.nombre', $params);

$grupos = filas('SELECT g.id, g.nombre FROM grupos g WHERE ' . $mio . ' AND g.estado = "activo" ORDER BY g.nombre');

if (get('exportar') === 'csv') {
    descargar_csv('estudiantes',
        ['Apellidos', 'Nombres', 'Correo', 'Grupos', 'Entregas', 'Calificadas', 'Por revisar', 'Avance %', 'Último acceso'],
        array_map(fn($e) => [
            $e['apellidos'], $e['nombre'], $e['email'], $e['grupos'],
            (int) $e['entregas'], (int) $e['calificadas'], (int) $e['por_revisar'],
            (int) $e['avance'], $e['ultimo_acceso'] ?? '',
        ], $estudiantes));
}

cabecera('Estudiantes', [
    'titulo' => 'Mis estudiantes',
    'sub'    => count($estudiantes) . ' estudiante(s) en tus grupos activos.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Estudiantes']],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/estudiantes.php?exportar=csv' . ($grupoF ? '&grupo=' . $grupoF : '')) . '">Exportar CSV</a>',
]);
?>
<form class="acciones-barra" method="get">
  <input type="search" name="q" value="<?= h($buscar) ?>" placeholder="Buscar por nombre o correo" class="crece">
  <select name="grupo">
    <option value="0">Todos mis grupos</option>
    <?php foreach ($grupos as $g): ?>
      <option value="<?= (int) $g['id'] ?>" <?= $grupoF === (int) $g['id'] ? 'selected' : '' ?>><?= h($g['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filtrar</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('portal/docente/estudiantes.php') ?>">Limpiar</a>
</form>

<?php if (!$estudiantes): ?>
  <?= vacio('Sin estudiantes', 'Agrégalos desde la página del grupo o comparte el código de matrícula.') ?>
<?php else: ?>
  <div class="panel panel-plano">
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Estudiante</th><th>Grupos</th><th style="min-width:130px">Avance</th><th class="num">Entregas</th><th>Último acceso</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($estudiantes as $e): ?>
          <tr>
            <td>
              <strong><?= h(trim($e['apellidos'] . ', ' . $e['nombre'])) ?></strong><br>
              <span class="txt-sm txt-muted"><?= h($e['email']) ?></span>
            </td>
            <td class="txt-sm"><?= h($e['grupos']) ?></td>
            <td><?= barra((int) ($e['avance'] ?? 0)) ?><span class="txt-sm txt-muted"><?= (int) ($e['avance'] ?? 0) ?>%</span></td>
            <td class="num txt-sm">
              <?= (int) $e['calificadas'] ?>/<?= (int) $e['entregas'] ?> calificadas
              <?php if ((int) $e['por_revisar']): ?><br><span class="chip chip-ambar"><?= (int) $e['por_revisar'] ?> por revisar</span><?php endif; ?>
              <?php if ((int) $e['rehacer']): ?><br><span class="chip chip-rojo"><?= (int) $e['rehacer'] ?> a rehacer</span><?php endif; ?>
            </td>
            <td class="txt-sm txt-muted"><?= $e['ultimo_acceso'] ? fecha_rel($e['ultimo_acceso']) : 'nunca' ?></td>
            <td class="acc">
              <a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/calificar.php?estado=entregada') ?>">Revisar</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php pie(); ?>
