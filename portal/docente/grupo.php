<?php
/**
 * Detalle de un grupo: estudiantes, matrícula, asignaciones y avance.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('docente', 'admin');
$id = get_int('id');

$g = fila('SELECT g.*, n.nombre AS nivel, n.grado, n.programa_ib, n.asignatura, n.id AS nivel_id
             FROM grupos g JOIN niveles n ON n.id = g.nivel_id WHERE g.id = ?', [$id]);
if (!$g || !puede_gestionar_grupo($g)) {
    flash_err('No encontramos ese grupo entre los tuyos.');
    redirigir('portal/docente/grupos.php');
}

// -------------------------------------------------------------- acciones --
if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    // --- agregar un estudiante existente o crearlo -------------------------
    if ($accion === 'agregar') {
        $email = mb_strtolower(post('email'));
        $est = fila('SELECT * FROM usuarios WHERE email = ?', [$email]);
        if (!$est) {
            [$ok, $res] = crear_usuario([
                'nombre'     => post('nombre') ?: 'Estudiante',
                'apellidos'  => post('apellidos'),
                'email'      => $email,
                'clave'      => post('clave') ?: 'Vcodepro' . random_int(1000, 9999),
                'rol'        => 'estudiante',
                'colegio_id' => $g['colegio_id'],
            ]);
            if (!$ok) { flash_err($res); redirigir('portal/docente/grupo.php?id=' . $id); }
            $estId = (int) $res;
            flash_ok('Estudiante creado y matriculado.');
        } elseif ($est['rol'] !== 'estudiante') {
            flash_err('Ese correo pertenece a una cuenta que no es de estudiante.');
            redirigir('portal/docente/grupo.php?id=' . $id);
        } else {
            $estId = (int) $est['id'];
            flash_ok('Estudiante matriculado en el grupo.');
        }
        if (!valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$id, $estId])) {
            insertar('grupo_estudiantes', ['grupo_id' => $id, 'estudiante_id' => $estId, 'estado' => 'activo']);
            foreach (filas('SELECT id FROM asignaciones WHERE grupo_id = ? AND estado = "abierta"', [$id]) as $a) {
                entrega_de((int) $a['id'], $estId);
            }
            notificar($estId, 'Te matricularon en ' . $g['nombre'],
                'Ya puedes ver las actividades del grupo.', 'portal/estudiante/actividades.php');
        }
        auditar('estudiante_matriculado', 'grupos', $id, $email);
        redirigir('portal/docente/grupo.php?id=' . $id);
    }

    // --- importación masiva desde texto o CSV ------------------------------
    if ($accion === 'importar') {
        $texto = post('lista');
        if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $texto = (string) file_get_contents($_FILES['csv']['tmp_name']);
        }
        $creados = 0; $matriculados = 0; $errores = [];
        foreach (preg_split('/\R/', $texto) ?: [] as $n => $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;
            $partes = array_map('trim', preg_split('/[;,\t]/', $linea) ?: []);
            if (count($partes) < 3) { $errores[] = 'Línea ' . ($n + 1) . ': faltan columnas.'; continue; }
            [$nom, $ape, $mail] = $partes;
            $mail = mb_strtolower($mail);
            $est = fila('SELECT id, rol FROM usuarios WHERE email = ?', [$mail]);
            if (!$est) {
                [$ok, $res] = crear_usuario([
                    'nombre' => $nom, 'apellidos' => $ape, 'email' => $mail,
                    'clave'  => $partes[3] ?? ('Vcodepro' . random_int(1000, 9999)),
                    'rol'    => 'estudiante', 'colegio_id' => $g['colegio_id'],
                ]);
                if (!$ok) { $errores[] = 'Línea ' . ($n + 1) . ': ' . $res; continue; }
                $estId = (int) $res; $creados++;
            } else {
                if ($est['rol'] !== 'estudiante') { $errores[] = 'Línea ' . ($n + 1) . ': el correo no es de un estudiante.'; continue; }
                $estId = (int) $est['id'];
            }
            if (!valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$id, $estId])) {
                insertar('grupo_estudiantes', ['grupo_id' => $id, 'estudiante_id' => $estId, 'estado' => 'activo']);
                foreach (filas('SELECT id FROM asignaciones WHERE grupo_id = ? AND estado = "abierta"', [$id]) as $a) {
                    entrega_de((int) $a['id'], $estId);
                }
                $matriculados++;
            }
        }
        auditar('importacion_estudiantes', 'grupos', $id, "creados=$creados matriculados=$matriculados");
        flash_ok("Importación terminada: $creados cuenta(s) nueva(s) y $matriculados matrícula(s).");
        foreach (array_slice($errores, 0, 5) as $er) flash_err($er);
        redirigir('portal/docente/grupo.php?id=' . $id);
    }

    // --- retirar / reactivar estudiante ------------------------------------
    if ($accion === 'retirar' || $accion === 'reactivar_est') {
        actualizar('grupo_estudiantes',
            ['estado' => $accion === 'retirar' ? 'retirado' : 'activo'],
            'grupo_id = :g AND estudiante_id = :e', ['g' => $id, 'e' => post_int('estudiante_id')]);
        flash_ok($accion === 'retirar' ? 'Estudiante retirado del grupo.' : 'Estudiante reactivado.');
        redirigir('portal/docente/grupo.php?id=' . $id);
    }

    // --- restablecer contraseña de un estudiante ---------------------------
    if ($accion === 'clave_est') {
        $estId = post_int('estudiante_id');
        if (valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$id, $estId])) {
            $nueva = 'Vcp' . codigo_aleatorio(6) . random_int(10, 99);
            cambiar_clave($estId, $nueva);
            flash_ok('Contraseña temporal generada: ' . $nueva . ' — entrégasela al estudiante y pídele cambiarla.');
        }
        redirigir('portal/docente/grupo.php?id=' . $id);
    }

    // --- ajustes del grupo --------------------------------------------------
    if ($accion === 'ajustes') {
        actualizar('grupos', [
            'nombre'       => post('nombre') ?: $g['nombre'],
            'periodo'      => post('periodo') ?: null,
            'jornada'      => post('jornada') ?: null,
            'ia_permitida' => isset($_POST['ia_permitida']) ? 1 : 0,
        ], 'id = :id', ['id' => $id]);
        flash_ok('Ajustes del grupo actualizados.');
        redirigir('portal/docente/grupo.php?id=' . $id);
    }
}

// ---------------------------------------------------------------- datos ---
$estudiantes = filas('SELECT u.id, u.nombre, u.apellidos, u.email, u.ultimo_acceso, ge.estado,
                             (SELECT ROUND(AVG(e.progreso)) FROM entregas e
                                JOIN asignaciones a ON a.id = e.asignacion_id
                               WHERE a.grupo_id = ? AND e.estudiante_id = u.id) AS avance,
                             (SELECT COUNT(*) FROM entregas e
                                JOIN asignaciones a ON a.id = e.asignacion_id
                               WHERE a.grupo_id = ? AND e.estudiante_id = u.id AND e.estado = "entregada") AS por_revisar
                        FROM grupo_estudiantes ge
                        JOIN usuarios u ON u.id = ge.estudiante_id
                       WHERE ge.grupo_id = ?
                    ORDER BY ge.estado, u.apellidos, u.nombre', [$id, $id, $id]);

$asignaciones = filas('SELECT a.*, ac.titulo, ac.codigo, ac.sesiones,
                              (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id AND e.estado = "revisada") AS calificadas,
                              (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id AND e.estado = "entregada") AS por_revisar,
                              (SELECT COUNT(*) FROM entregas e WHERE e.asignacion_id = a.id) AS total,
                              (SELECT ROUND(AVG(e.progreso)) FROM entregas e WHERE e.asignacion_id = a.id) AS avance
                         FROM asignaciones a JOIN actividades ac ON ac.id = a.actividad_id
                        WHERE a.grupo_id = ? ORDER BY a.fecha_entrega DESC', [$id]);

$r = resumen_grupo($id);
$activos = array_values(array_filter($estudiantes, fn($e) => $e['estado'] === 'activo'));

cabecera($g['nombre'], [
    'titulo' => $g['nombre'],
    'sub'    => $g['grado'] . ' · ' . $g['programa_ib'] . ' · ' . $g['asignatura'] . ' · ' . $g['periodo'] . ' de ' . $g['anio'],
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Mis grupos', 'portal/docente/grupos.php'], [$g['nombre']]],
    'acciones' => '<a class="btn btn-ghost" href="' . url('portal/docente/seguimiento.php?grupo=' . $id) . '">Matriz de avance</a>'
                . '<a class="btn" href="' . url('portal/docente/banco.php?nivel=' . (int) $g['nivel_id'] . '&grupo=' . $id) . '">Asignar actividad</a>',
]);
?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Estudiantes activos', count($activos)) ?>
  <?= metrica('Actividades asignadas', count($asignaciones)) ?>
  <?= metrica('Por revisar', (int) ($r['por_revisar'] ?? 0), null, 'warn') ?>
  <?= metrica('Avance promedio', ((int) ($r['avance'] ?? 0)) . '%', null, 'brand') ?>
</div>

<div class="panel">
  <div class="panel-h">
    <div>
      <h2>Código de matrícula</h2>
      <p>Compártelo en clase: los estudiantes se matriculan solos desde su panel.</p>
    </div>
    <code class="mono copiar" data-copiar="<?= h($g['codigo']) ?>"
          style="font-size:1.35rem;letter-spacing:.2em;padding:.5rem .9rem;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt)"><?= h($g['codigo']) ?></code>
  </div>
</div>

<div class="rejilla rej-lat">
  <div>
    <!-- ============================ ESTUDIANTES ============================ -->
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Estudiantes</h2>
        <input type="search" data-filtra="#tabla-estudiantes" placeholder="Filtrar por nombre o correo" style="max-width:250px">
      </div>
      <?php if (!$estudiantes): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Todavía no hay estudiantes. Agrégalos con el formulario de la derecha o comparte el código.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla" id="tabla-estudiantes">
            <thead><tr><th>Estudiante</th><th>Correo</th><th style="min-width:130px">Avance</th><th>Último acceso</th><th class="acc">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($estudiantes as $e): ?>
              <tr<?= $e['estado'] !== 'activo' ? ' style="opacity:.55"' : '' ?>>
                <td>
                  <strong><?= h(trim($e['apellidos'] . ', ' . $e['nombre'])) ?></strong>
                  <?php if ($e['estado'] !== 'activo'): ?> <?= etiqueta_estado($e['estado']) ?><?php endif; ?>
                  <?php if ((int) $e['por_revisar'] > 0): ?> <span class="chip chip-ambar"><?= (int) $e['por_revisar'] ?> por revisar</span><?php endif; ?>
                </td>
                <td class="txt-sm txt-muted"><?= h($e['email']) ?></td>
                <td>
                  <?= barra((int) ($e['avance'] ?? 0)) ?>
                  <span class="txt-sm txt-muted"><?= (int) ($e['avance'] ?? 0) ?>%</span>
                </td>
                <td class="txt-sm txt-muted"><?= $e['ultimo_acceso'] ? fecha_rel($e['ultimo_acceso']) : 'nunca' ?></td>
                <td class="acc">
                  <form method="post" class="btn-fila">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="estudiante_id" value="<?= (int) $e['id'] ?>">
                    <a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/seguimiento.php?grupo=' . $id . '&estudiante=' . (int) $e['id']) ?>">Ver</a>
                    <button class="btn btn-xs btn-ghost" name="accion" value="clave_est" data-confirmar="¿Generar una contraseña temporal para este estudiante?">Clave</button>
                    <?php if ($e['estado'] === 'activo'): ?>
                      <button class="btn btn-xs btn-ghost" name="accion" value="retirar" data-confirmar="¿Retirar del grupo? Conservará su trabajo.">Retirar</button>
                    <?php else: ?>
                      <button class="btn btn-xs btn-ghost" name="accion" value="reactivar_est">Reactivar</button>
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

    <!-- =========================== ASIGNACIONES =========================== -->
    <div class="panel panel-plano">
      <div class="panel-h">
        <h2>Actividades asignadas</h2>
        <a class="txt-sm" href="<?= url('portal/docente/banco.php?nivel=' . (int) $g['nivel_id'] . '&grupo=' . $id) ?>">Asignar otra</a>
      </div>
      <?php if (!$asignaciones): ?>
        <div style="padding:20px"><p class="txt-muted mb-0">Sin actividades asignadas. Elige una del banco correspondiente a <?= h($g['grado']) ?>.</p></div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Actividad</th><th>Entrega</th><th style="min-width:130px">Avance</th><th class="num">Estado</th><th class="acc">&nbsp;</th></tr></thead>
            <tbody>
            <?php foreach ($asignaciones as $a): $d = dias_para($a['fecha_entrega']); ?>
              <tr>
                <td>
                  <strong><?= h($a['titulo']) ?></strong><br>
                  <span class="act-cod"><?= h($a['codigo']) ?></span> <?= etiqueta_estado($a['estado']) ?>
                </td>
                <td>
                  <?= fecha($a['fecha_entrega']) ?><br>
                  <span class="txt-sm txt-muted"><?= $d < 0 ? 'venció' : ($d === 0 ? 'hoy' : 'en ' . $d . ' d') ?></span>
                </td>
                <td>
                  <?= barra((int) ($a['avance'] ?? 0)) ?>
                  <span class="txt-sm txt-muted"><?= (int) ($a['avance'] ?? 0) ?>%</span>
                </td>
                <td class="num txt-sm">
                  <?= (int) $a['calificadas'] ?>/<?= (int) $a['total'] ?> calificadas
                  <?php if ((int) $a['por_revisar'] > 0): ?><br><span class="chip chip-ambar"><?= (int) $a['por_revisar'] ?> por revisar</span><?php endif; ?>
                </td>
                <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/asignaciones.php?id=' . (int) $a['id']) ?>">Abrir</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================================ LATERAL ============================= -->
  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="agregar">
      <div class="panel-h"><h3>Agregar un estudiante</h3></div>
      <div class="campo">
        <label for="email">Correo institucional</label>
        <input type="email" id="email" name="email" required placeholder="nombre.apellido@colegio.edu.co">
        <span class="pista">Si la cuenta ya existe solo se matricula; si no, se crea.</span>
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="nombre">Nombres</label><input type="text" id="nombre" name="nombre"></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos"></div>
      </div>
      <div class="campo">
        <label for="clave">Contraseña inicial</label>
        <input type="text" id="clave" name="clave" placeholder="Se genera automáticamente si la dejas vacía">
      </div>
      <button class="btn btn-block" type="submit">Agregar</button>
    </form>

    <form method="post" class="panel" enctype="multipart/form-data">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="importar">
      <div class="panel-h"><h3>Importar la lista del curso</h3></div>
      <div class="campo">
        <label for="lista">Pegar la lista</label>
        <textarea id="lista" name="lista" style="min-height:120px;font-family:var(--mono);font-size:.85rem"
                  placeholder="Mariana;Acosta Rivera;mariana.acosta@colegio.edu.co
Samuel;Bermúdez Lozano;samuel.bermudez@colegio.edu.co"></textarea>
        <span class="pista">Una línea por estudiante: nombres; apellidos; correo; contraseña (opcional).</span>
      </div>
      <div class="campo">
        <label for="csv">…o subir un archivo CSV</label>
        <input type="file" id="csv" name="csv" accept=".csv,.txt">
      </div>
      <button class="btn btn-block" type="submit">Importar</button>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="ajustes">
      <div class="panel-h"><h3>Ajustes del grupo</h3></div>
      <div class="campo">
        <label for="gnombre">Nombre</label>
        <input type="text" id="gnombre" name="nombre" value="<?= h($g['nombre']) ?>">
      </div>
      <div class="campo-fila">
        <div class="campo"><label for="periodo">Periodo</label><input type="text" id="periodo" name="periodo" value="<?= h($g['periodo']) ?>"></div>
        <div class="campo"><label for="jornada">Jornada</label><input type="text" id="jornada" name="jornada" value="<?= h($g['jornada']) ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="ia_permitida" value="1" <?= $g['ia_permitida'] ? 'checked' : '' ?>><span>Asistencia de IA en modo pedagógico</span></label>
      <button class="btn btn-block btn-ghost" type="submit">Guardar ajustes</button>
    </form>
  </aside>
</div>
<?php pie(); ?>
