<?php
/**
 * Panel general del administrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');

$us = fila('SELECT COUNT(*) AS total,
                   SUM(rol = "estudiante") AS estudiantes,
                   SUM(rol = "docente")    AS docentes,
                   SUM(rol = "cliente")    AS clientes,
                   SUM(estado = "pendiente") AS pendientes,
                   SUM(estado = "suspendido") AS suspendidos
              FROM usuarios');

$ac = fila('SELECT COUNT(*) AS actividades,
                   (SELECT COUNT(*) FROM niveles) AS niveles,
                   (SELECT COUNT(*) FROM grupos WHERE estado = "activo") AS grupos,
                   (SELECT COUNT(*) FROM asignaciones) AS asignaciones
              FROM actividades');

$en = fila('SELECT COUNT(*) AS total,
                   SUM(estado = "entregada") AS por_revisar,
                   SUM(estado = "revisada")  AS revisadas,
                   ROUND(AVG(progreso))      AS avance
              FROM entregas');

$com = fila('SELECT (SELECT COUNT(*) FROM licencias WHERE estado = "activa") AS licencias,
                    (SELECT COALESCE(SUM(cupo),0) FROM licencias WHERE estado = "activa") AS cupo,
                    (SELECT COUNT(*) FROM licencia_puestos WHERE estado = "activo") AS puestos,
                    (SELECT COUNT(*) FROM facturas WHERE estado = "pendiente") AS facturas,
                    (SELECT COUNT(*) FROM tickets WHERE estado IN ("abierto","en_proceso")) AS tickets,
                    (SELECT COUNT(*) FROM mensajes_contacto WHERE estado = "nuevo") AS mensajes');

$porNivel = filas('SELECT n.grado, n.nombre, n.programa_ib,
                          (SELECT COUNT(*) FROM actividades a WHERE a.nivel_id = n.id) AS actividades,
                          (SELECT COUNT(*) FROM grupos g WHERE g.nivel_id = n.id AND g.estado = "activo") AS grupos,
                          (SELECT COUNT(DISTINCT ge.estudiante_id) FROM grupos g
                             JOIN grupo_estudiantes ge ON ge.grupo_id = g.id
                            WHERE g.nivel_id = n.id AND ge.estado = "activo") AS estudiantes,
                          (SELECT ROUND(AVG(e.progreso)) FROM grupos g
                             JOIN asignaciones a2 ON a2.grupo_id = g.id
                             JOIN entregas e ON e.asignacion_id = a2.id
                            WHERE g.nivel_id = n.id) AS avance
                     FROM niveles n ORDER BY n.orden');

$actividad = filas('SELECT a.*, CONCAT(u.nombre, " ", u.apellidos) AS usuario, u.rol
                      FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
                  ORDER BY a.id DESC LIMIT 12');

$pendientes = filas('SELECT id, nombre, apellidos, email, creado_en FROM usuarios
                      WHERE estado = "pendiente" ORDER BY id DESC LIMIT 5');

cabecera('Panel general', [
    'titulo' => 'Panel general',
    'sub'    => 'Estado del portal académico, del currículo y de la operación comercial.',
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/admin/informes.php') . '">Informes</a>'
                . '<a class="btn" href="' . url('portal/admin/usuarios.php?nuevo=1') . '">Crear usuario</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Usuarios', (int) $us['total'], (int) $us['estudiantes'] . ' estudiantes · ' . (int) $us['docentes'] . ' docentes') ?>
  <?= metrica('Actividades publicadas', (int) $ac['actividades'], (int) $ac['niveles'] . ' niveles del plan', 'brand') ?>
  <?= metrica('Grupos activos', (int) $ac['grupos'], (int) $ac['asignaciones'] . ' asignaciones') ?>
  <?= metrica('Avance general', ((int) ($en['avance'] ?? 0)) . '%', (int) $en['total'] . ' entregas registradas') ?>
</div>

<?php if ((int) $us['pendientes'] > 0 || (int) $com['mensajes'] > 0 || (int) $com['tickets'] > 0): ?>
<div class="aviso aviso-info">
  <div>
    <strong>Pendientes de atención:</strong>
    <?php $p = [];
      if ((int) $us['pendientes']) $p[] = '<a href="' . url('portal/admin/usuarios.php?estado=pendiente') . '">' . (int) $us['pendientes'] . ' cuenta(s) por aprobar</a>';
      if ((int) $com['tickets'])   $p[] = '<a href="' . url('portal/admin/tickets.php') . '">' . (int) $com['tickets'] . ' ticket(s) de soporte</a>';
      if ((int) $com['mensajes'])  $p[] = '<a href="' . url('portal/admin/mensajes.php') . '">' . (int) $com['mensajes'] . ' mensaje(s) del sitio</a>';
      echo implode(' · ', $p);
    ?>
  </div>
</div>
<?php endif; ?>

<div class="rejilla rej-lat">
  <div>
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Currículo por nivel</h2>
        <a class="txt-sm" href="<?= url('portal/admin/actividades.php') ?>">Administrar actividades</a>
      </div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Nivel</th><th>Programa</th><th class="num">Actividades</th><th class="num">Grupos</th><th class="num">Estudiantes</th><th style="min-width:120px">Avance</th></tr></thead>
          <tbody>
          <?php foreach ($porNivel as $n): ?>
            <tr>
              <td><strong><?= h($n['grado']) ?></strong><br><span class="txt-sm txt-muted"><?= h(corte($n['nombre'], 34)) ?></span></td>
              <td class="txt-sm"><?= h($n['programa_ib']) ?></td>
              <td class="num"><?= (int) $n['actividades'] ?></td>
              <td class="num"><?= (int) $n['grupos'] ?></td>
              <td class="num"><?= (int) $n['estudiantes'] ?></td>
              <td><?= barra((int) ($n['avance'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Actividad reciente del sistema</h2>
        <a class="txt-sm" href="<?= url('portal/admin/auditoria.php') ?>">Ver auditoría</a>
      </div>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Acción</th><th>Usuario</th><th>Detalle</th><th>Cuándo</th></tr></thead>
          <tbody>
          <?php foreach ($actividad as $a): ?>
            <tr>
              <td><span class="mono txt-sm"><?= h($a['accion']) ?></span></td>
              <td class="txt-sm"><?= h($a['usuario'] ?? 'sistema') ?><?= $a['rol'] ? ' <span class="chip chip-gris">' . h(ROLES[$a['rol']]) . '</span>' : '' ?></td>
              <td class="txt-sm txt-muted"><?= h(corte($a['detalle'] ?? ($a['entidad'] ?? ''), 46)) ?></td>
              <td class="txt-sm txt-muted"><?= fecha_rel($a['creado_en']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Estado comercial</h3></div>
      <dl class="dl">
        <dt>Licencias activas</dt><dd><?= (int) $com['licencias'] ?></dd>
        <dt>Cupo contratado</dt><dd><?= (int) $com['cupo'] ?> puestos</dd>
        <dt>Puestos asignados</dt><dd><?= (int) $com['puestos'] ?></dd>
        <dt>Facturas pendientes</dt><dd><?= (int) $com['facturas'] ?></dd>
        <dt>Tickets abiertos</dt><dd><?= (int) $com['tickets'] ?></dd>
      </dl>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= url('portal/admin/licencias.php') ?>">Administrar licencias</a>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Cuentas por aprobar</h3></div>
      <?php if (!$pendientes): ?>
        <p class="txt-muted mb-0">No hay solicitudes pendientes.</p>
      <?php else: ?>
        <?php foreach ($pendientes as $p): ?>
          <p class="mb-0" style="padding:.5rem 0;border-bottom:1px solid var(--border-soft)">
            <strong><?= h(trim($p['nombre'] . ' ' . $p['apellidos'])) ?></strong><br>
            <span class="txt-sm txt-muted"><?= h($p['email']) ?> · <?= fecha_rel($p['creado_en']) ?></span>
          </p>
        <?php endforeach; ?>
        <a class="btn btn-sm mt-2" href="<?= url('portal/admin/usuarios.php?estado=pendiente') ?>">Revisar solicitudes</a>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Revisión académica</h3></div>
      <dl class="dl">
        <dt>Entregas por revisar</dt><dd><?= (int) ($en['por_revisar'] ?? 0) ?></dd>
        <dt>Entregas calificadas</dt><dd><?= (int) ($en['revisadas'] ?? 0) ?></dd>
        <dt>Cuentas suspendidas</dt><dd><?= (int) ($us['suspendidos'] ?? 0) ?></dd>
      </dl>
    </div>
  </aside>
</div>
<?php pie(); ?>
