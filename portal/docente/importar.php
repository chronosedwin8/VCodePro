<?php
/**
 * Importación de cursos y estudiantes desde la matrícula de Phidias.
 *
 * Tres formas de trabajar:
 *   1. Un grupo del portal por cada curso de Phidias, con su mismo nombre (K10C).
 *   2. Un solo grupo con nombre propio y estudiantes tomados de varios cursos.
 *   3. Agregar estudiantes a un grupo que ya existe.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/phidias.php';

$u = exigir_rol('docente', 'admin');

$niveles  = filas('SELECT id, grado, nombre FROM niveles ORDER BY orden');
$docentes = es('admin')
    ? filas('SELECT id, nombre, apellidos FROM usuarios WHERE rol = "docente" AND estado = "activo" ORDER BY apellidos')
    : [];
$misGrupos = filas('SELECT g.id, g.nombre, g.codigo, n.grado FROM grupos g JOIN niveles n ON n.id = g.nivel_id
                     WHERE ' . (es('admin') ? '1=1' : 'g.docente_id = ' . (int) $u['id']) . ' AND g.estado = "activo"
                  ORDER BY g.nombre');

// ------------------------------------------------- descarga de credenciales --
if (get('descargar') === 'credenciales') {
    iniciar_sesion();
    $cred = $_SESSION['phidias_credenciales'] ?? [];
    if (!$cred) { flash_err('No hay credenciales nuevas para descargar.'); redirigir('portal/docente/importar.php'); }
    descargar_csv('credenciales-importacion-' . date('Ymd-His'),
        ['Código', 'Estudiante', 'Correo', 'Contraseña inicial'], $cred);
}

// ------------------------------------------------------------------ acciones --
$paso   = 'elegir';
$aviso  = null;
$cursos = [];
$sincronizado = 0;

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    // --- refrescar la caché ------------------------------------------------
    if ($accion === 'refrescar') {
        [$ok, $datos, $cuando, $err] = array_pad(phidias_cursos(true), 4, null);
        if ($ok) {
            $n = array_sum(array_map(fn($c) => count($c['estudiantes']), $datos));
            auditar('phidias_sincronizado', null, null, count($datos) . ' cursos, ' . $n . ' estudiantes');
            flash_ok('Matrícula actualizada: ' . count($datos) . ' cursos y ' . $n . ' estudiantes.');
        } else {
            flash_err($datos);
        }
        redirigir('portal/docente/importar.php');
    }

    // --- paso 2: revisar los estudiantes -----------------------------------
    if ($accion === 'revisar') {
        $paso = 'revisar';
    }

    // --- paso 3: importar de verdad ----------------------------------------
    if ($accion === 'importar') {
        [$ok, $cursos] = array_pad(phidias_cursos(), 2, null);
        if (!$ok) { flash_err((string) $cursos); redirigir('portal/docente/importar.php'); }

        // Índice curso => id externo => estudiante
        $indice = [];
        foreach ($cursos as $c) foreach ($c['estudiantes'] as $e) $indice[$c['curso']][$e['id_externo']] = $e;

        // Estudiantes marcados, agrupados por curso de origen.
        $porCurso = [];
        foreach ((array) ($_POST['est'] ?? []) as $clave) {
            [$curso, $idExt] = array_pad(explode('::', (string) $clave, 2), 2, '');
            if (isset($indice[$curso][(int) $idExt])) $porCurso[$curso][] = $indice[$curso][(int) $idExt];
        }
        if (!$porCurso) { flash_err('No marcaste ningún estudiante.'); redirigir('portal/docente/importar.php'); }

        $modo       = post('modo');
        $docenteId  = es('admin') ? (post_int('docente_id') ?: (int) $u['id']) : (int) $u['id'];
        $anio       = post_int('anio') ?: (int) date('Y');
        $periodo    = post('periodo') ?: ajuste('periodo_actual', 'Periodo 1');
        $colegioId  = $u['colegio_id'] ?: (int) valor('SELECT id FROM colegios ORDER BY id LIMIT 1', [], 0) ?: null;

        $credenciales = [];
        $informe = ['grupos' => [], 'creados' => 0, 'existentes' => 0, 'errores' => [], 'matriculas' => 0];

        /** Crea un grupo del portal y devuelve su id. */
        $crearGrupo = function (string $nombre, int $nivelId, ?string $cursoExterno) use ($docenteId, $anio, $periodo, $colegioId, &$informe): int {
            $existente = fila('SELECT id, codigo FROM grupos WHERE nombre = ? AND anio = ?', [$nombre, $anio]);
            if ($existente) {
                $informe['grupos'][] = ['nombre' => $nombre, 'codigo' => $existente['codigo'], 'id' => (int) $existente['id'], 'nuevo' => false];
                return (int) $existente['id'];
            }
            do { $codigo = codigo_aleatorio(8); } while (valor('SELECT id FROM grupos WHERE codigo = ?', [$codigo]));
            $id = insertar('grupos', [
                'colegio_id'    => $colegioId,
                'docente_id'    => $docenteId,
                'nivel_id'      => $nivelId,
                'nombre'        => $nombre,
                'anio'          => $anio,
                'periodo'       => $periodo,
                'codigo'        => $codigo,
                'curso_externo' => $cursoExterno,
                'ia_permitida'  => 1,
                'estado'        => 'activo',
            ]);
            $informe['grupos'][] = ['nombre' => $nombre, 'codigo' => $codigo, 'id' => $id, 'nuevo' => true];
            return $id;
        };

        /** Matricula un estudiante en un grupo y le abre las entregas pendientes. */
        $matricular = function (int $grupoId, int $estudianteId) use (&$informe): void {
            if (!valor('SELECT id FROM grupo_estudiantes WHERE grupo_id = ? AND estudiante_id = ?', [$grupoId, $estudianteId])) {
                insertar('grupo_estudiantes', ['grupo_id' => $grupoId, 'estudiante_id' => $estudianteId, 'estado' => 'activo']);
                $informe['matriculas']++;
            }
            foreach (filas('SELECT id FROM asignaciones WHERE grupo_id = ? AND estado = "abierta"', [$grupoId]) as $a) {
                entrega_de((int) $a['id'], $estudianteId);
            }
        };

        // Destinos según el modo elegido.
        $destinos = [];   // curso de Phidias => id de grupo del portal
        if ($modo === 'existente') {
            $gid = post_int('grupo_id');
            $g = fila('SELECT * FROM grupos WHERE id = ?', [$gid]);
            if (!$g || !puede_gestionar_grupo($g)) { flash_err('No puedes usar ese grupo.'); redirigir('portal/docente/importar.php'); }
            $informe['grupos'][] = ['nombre' => $g['nombre'], 'codigo' => $g['codigo'], 'id' => (int) $g['id'], 'nuevo' => false];
            foreach (array_keys($porCurso) as $curso) $destinos[$curso] = (int) $g['id'];

        } elseif ($modo === 'unico') {
            $nombre = post('nombre_unico');
            $nivelId = post_int('nivel_unico');
            if ($nombre === '' || !$nivelId) { flash_err('El grupo nuevo necesita nombre y nivel.'); redirigir('portal/docente/importar.php'); }
            $gid = $crearGrupo($nombre, $nivelId, implode(', ', array_keys($porCurso)));
            foreach (array_keys($porCurso) as $curso) $destinos[$curso] = $gid;

        } else { // por_curso
            foreach (array_keys($porCurso) as $curso) {
                $nombre  = post('nombre_' . $curso) ?: $curso;
                $nivelId = post_int('nivel_' . $curso);
                if (!$nivelId) { flash_err('Falta el nivel del curso ' . $curso . '.'); redirigir('portal/docente/importar.php'); }
                $destinos[$curso] = $crearGrupo($nombre, $nivelId, $curso);
            }
        }

        // Alta de cuentas y matrícula.
        foreach ($porCurso as $curso => $lista) {
            foreach ($lista as $e) {
                [$id, $estado] = phidias_asegurar_estudiante($e, $colegioId, $credenciales);
                if (str_starts_with($estado, 'error:')) {
                    $informe['errores'][] = trim($e['apellidos'] . ', ' . $e['nombre']) . ' — ' . substr($estado, 6);
                    continue;
                }
                $informe[$estado === 'creado' ? 'creados' : 'existentes']++;
                $matricular($destinos[$curso], $id);
            }
        }

        iniciar_sesion();
        $_SESSION['phidias_informe'] = $informe;
        $_SESSION['phidias_credenciales'] = $credenciales;
        auditar('phidias_importacion', null, null,
            $informe['creados'] . ' cuentas nuevas, ' . $informe['matriculas'] . ' matrículas, ' . count($informe['grupos']) . ' grupos');
        flash_ok('Importación terminada.');
        redirigir('portal/docente/importar.php?resultado=1');
    }
}

// ------------------------------------------------------------------- datos --
[$ok, $resp, $sincronizado, $errCache] = array_pad(phidias_cursos(), 4, null);
if ($ok) { $cursos = $resp; } else { $aviso = (string) $resp; }
if (!empty($errCache)) $aviso = 'Se muestran los datos guardados. ' . $errCache;

// Cursos marcados en el paso 1.
$seleccion = array_values(array_intersect(
    array_map(fn($c) => $c['curso'], $cursos),
    (array) ($_POST['cursos'] ?? [])
));
if ($paso === 'revisar' && !$seleccion) {
    flash_err('Marca al menos un curso para continuar.');
    $paso = 'elegir';
}

$modo = post('modo') ?: 'por_curso';

// Agrupación para la vista: KLASSE => cursos
$porKlasse = [];
foreach ($cursos as $c) $porKlasse[$c['etapa'] . ' · ' . $c['klasse']][] = $c;

$informe = null;
if (get('resultado') === '1') {
    iniciar_sesion();
    $informe = $_SESSION['phidias_informe'] ?? null;
}

cabecera('Importar desde Phidias', [
    'titulo' => 'Importar desde Phidias',
    'sub'    => 'Trae los cursos y los estudiantes matriculados directamente del sistema del colegio.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Mis grupos', 'portal/docente/grupos.php'], ['Importar']],
    'acciones' => '<form method="post" style="display:inline">' . csrf_campo()
        . '<button class="btn btn-ghost" name="accion" value="refrescar">Actualizar matrícula</button></form>',
]);
?>

<?php if ($aviso): ?>
  <div class="aviso aviso-warn"><div><?= h($aviso) ?></div></div>
<?php endif; ?>

<?php if (!phidias_configurada()): ?>
  <div class="aviso aviso-err">
    <div>
      <strong>Falta el token de Phidias.</strong> Configúralo en
      <a href="<?= url('portal/admin/ajustes.php') ?>">Ajustes</a> o en <code>includes/config.local.php</code>.
    </div>
  </div>
<?php endif; ?>

<?php // ===================================================== RESULTADO ===== ?>
<?php if ($informe): ?>
  <div class="rejilla rej-4 mb-2">
    <?= metrica('Grupos', count($informe['grupos'])) ?>
    <?= metrica('Cuentas creadas', (int) $informe['creados'], null, 'ok') ?>
    <?= metrica('Cuentas reutilizadas', (int) $informe['existentes']) ?>
    <?= metrica('Matrículas nuevas', (int) $informe['matriculas'], null, 'brand') ?>
  </div>

  <div class="panel panel-plano">
    <div class="panel-h">
      <h2>Grupos del portal</h2>
      <?php if (!empty($_SESSION['phidias_credenciales'])): ?>
        <a class="btn btn-sm" href="<?= url('portal/docente/importar.php?descargar=credenciales') ?>">
          Descargar credenciales (<?= count($_SESSION['phidias_credenciales']) ?>)
        </a>
      <?php endif; ?>
    </div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Grupo</th><th>Código de matrícula</th><th>Estado</th><th class="acc">&nbsp;</th></tr></thead>
        <tbody>
        <?php foreach ($informe['grupos'] as $g): ?>
          <tr>
            <td><strong><?= h($g['nombre']) ?></strong></td>
            <td><code class="mono copiar" data-copiar="<?= h($g['codigo']) ?>"><?= h($g['codigo']) ?></code></td>
            <td><?= $g['nuevo'] ? '<span class="chip chip-verde">Creado</span>' : '<span class="chip chip-gris">Ya existía</span>' ?></td>
            <td class="acc"><a class="btn btn-xs btn-ghost" href="<?= url('portal/docente/grupo.php?id=' . (int) $g['id']) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($informe['errores']): ?>
    <div class="panel">
      <div class="panel-h"><h2>No se pudieron importar (<?= count($informe['errores']) ?>)</h2></div>
      <ul class="txt-sm">
        <?php foreach ($informe['errores'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
      <p class="txt-sm txt-muted mb-0">Suele deberse a estudiantes sin correo en Phidias o a un correo ya usado por otra cuenta del portal.</p>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['phidias_credenciales'])): ?>
    <div class="aviso aviso-info">
      <div>
        Las contraseñas iniciales se generaron al azar y <strong>solo pueden descargarse ahora</strong>:
        no se guardan en texto plano. Entrégalas por un canal seguro y pide a los estudiantes cambiarlas.
      </div>
    </div>
  <?php endif; ?>

  <p><a class="btn btn-ghost" href="<?= url('portal/docente/importar.php') ?>">Hacer otra importación</a></p>
  <?php pie(); exit; ?>
<?php endif; ?>

<?php // ================================================ PASO 2 · REVISAR === ?>
<?php if ($paso === 'revisar'):
    $sel = array_values(array_filter($cursos, fn($c) => in_array($c['curso'], $seleccion, true)));
    $totalEst = array_sum(array_map(fn($c) => count($c['estudiantes']), $sel));
?>
  <form method="post" data-avisar>
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="importar">
    <input type="hidden" name="modo" value="<?= h($modo) ?>">

    <div class="panel">
      <div class="panel-h">
        <div>
          <h2>Paso 2 · Revisa y confirma</h2>
          <p><?= count($sel) ?> curso(s) · <?= $totalEst ?> estudiantes. Desmarca los que no quieras traer.</p>
        </div>
      </div>

      <div class="campo-fila-3">
        <?php if (es('admin')): ?>
          <div class="campo">
            <label for="docente_id">Docente responsable</label>
            <select id="docente_id" name="docente_id">
              <?php foreach ($docentes as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= h(trim($d['apellidos'] . ', ' . $d['nombre'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="campo">
          <label for="anio">Año escolar</label>
          <input type="number" id="anio" name="anio" value="<?= (int) date('Y') ?>" min="2020" max="2100">
        </div>
        <div class="campo">
          <label for="periodo">Periodo</label>
          <input type="text" id="periodo" name="periodo" value="<?= h(ajuste('periodo_actual', 'Periodo 1')) ?>">
        </div>
      </div>

      <?php if ($modo === 'unico'): ?>
        <div class="campo-fila">
          <div class="campo">
            <label for="nombre_unico">Nombre del grupo nuevo</label>
            <input type="text" id="nombre_unico" name="nombre_unico" required
                   value="<?= h(post('nombre_unico') ?: 'Electiva de Tecnología · ' . implode(' + ', $seleccion)) ?>">
          </div>
          <div class="campo">
            <label for="nivel_unico">Nivel del plan de aula</label>
            <select id="nivel_unico" name="nivel_unico" required>
              <?php $sug = $sel[0]['nivel_id'] ?? 0; foreach ($niveles as $n): ?>
                <option value="<?= (int) $n['id'] ?>" <?= (int) $n['id'] === (int) $sug ? 'selected' : '' ?>>
                  <?= h($n['grado'] . ' · ' . $n['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php elseif ($modo === 'existente'): ?>
        <div class="campo">
          <label for="grupo_id">Agregar al grupo</label>
          <select id="grupo_id" name="grupo_id" required>
            <?php foreach ($misGrupos as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= post_int('grupo_id') === (int) $g['id'] ? 'selected' : '' ?>>
                <?= h($g['nombre'] . ' · ' . $g['grado'] . ' · ' . $g['codigo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>

    <?php foreach ($sel as $c): ?>
      <div class="panel">
        <div class="panel-h">
          <div>
            <h2><?= h($c['curso']) ?></h2>
            <p><?= h($c['etapa']) ?> · <?= h($c['klasse']) ?> · <?= count($c['estudiantes']) ?> estudiantes</p>
          </div>
          <label class="check" style="margin:0">
            <input type="checkbox" checked data-marcar-todo="[data-curso='<?= h($c['curso']) ?>']">
            <span>Marcar todos</span>
          </label>
        </div>

        <?php if ($modo === 'por_curso'): ?>
          <div class="campo-fila">
            <div class="campo">
              <label for="n_<?= h($c['curso']) ?>">Nombre del grupo en el portal</label>
              <input type="text" id="n_<?= h($c['curso']) ?>" name="nombre_<?= h($c['curso']) ?>" value="<?= h($c['curso']) ?>" required>
            </div>
            <div class="campo">
              <label for="v_<?= h($c['curso']) ?>">Nivel del plan de aula</label>
              <select id="v_<?= h($c['curso']) ?>" name="nivel_<?= h($c['curso']) ?>" required>
                <?php foreach ($niveles as $n): ?>
                  <option value="<?= (int) $n['id'] ?>" <?= (int) $n['id'] === (int) $c['nivel_id'] ? 'selected' : '' ?>>
                    <?= h($n['grado'] . ' · ' . $n['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (!$c['nivel_id']): ?>
                <span class="pista">El curso <?= h($c['curso']) ?> no corresponde a ningún nivel del plan (6.º a 12.º). Elige uno a mano.</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="tabla-caja">
          <table class="tabla tabla-mini">
            <thead><tr><th style="width:40px">&nbsp;</th><th>Estudiante</th><th>Código</th><th>Correo</th><th>Matrícula</th></tr></thead>
            <tbody>
            <?php foreach ($c['estudiantes'] as $e):
                $sinCorreo = $e['email'] === '';
            ?>
              <tr<?= $sinCorreo ? ' style="opacity:.5"' : '' ?>>
                <td>
                  <input type="checkbox" name="est[]" data-curso="<?= h($c['curso']) ?>"
                         value="<?= h($c['curso'] . '::' . $e['id_externo']) ?>"
                         <?= $sinCorreo ? 'disabled' : 'checked' ?>>
                </td>
                <td><?= h(trim($e['apellidos'] . ', ' . $e['nombre'])) ?></td>
                <td class="mono txt-sm"><?= h($e['codigo'] ?: '—') ?></td>
                <td class="txt-sm txt-muted"><?= $sinCorreo ? 'sin correo en Phidias' : h($e['email']) ?></td>
                <td><?= $e['estado'] === 'activo' ? '<span class="chip chip-verde">Activo</span>' : '<span class="chip chip-gris">' . h($e['estado'] ?: '—') . '</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="form-acc">
      <button class="btn btn-lg" type="submit">Importar al portal</button>
      <a class="btn btn-ghost" href="<?= url('portal/docente/importar.php') ?>">Volver</a>
    </div>
  </form>
  <?php pie(); exit; ?>
<?php endif; ?>

<?php // ================================================= PASO 1 · ELEGIR === ?>
<div class="rejilla rej-4 mb-2">
  <?= metrica('Cursos en Phidias', count($cursos)) ?>
  <?= metrica('Estudiantes matriculados', array_sum(array_map(fn($c) => count($c['estudiantes']), $cursos))) ?>
  <?= metrica('Cursos del plan (6.º a 12.º)', count(array_filter($cursos, fn($c) => $c['nivel_id'] !== null)), null, 'brand') ?>
  <?= metrica('Última sincronización', $sincronizado ? fecha_rel(date('Y-m-d H:i:s', $sincronizado)) : 'nunca') ?>
</div>

<?php if (!$cursos): ?>
  <?= vacio('Sin datos de matrícula', 'Pulsa «Actualizar matrícula» para traerlos de Phidias.') ?>
<?php else: ?>
<form method="post">
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="revisar">

  <div class="panel">
    <div class="panel-h"><h2>Paso 1 · Qué quieres hacer</h2></div>
    <label class="check">
      <input type="radio" name="modo" value="por_curso" <?= $modo === 'por_curso' ? 'checked' : '' ?>>
      <span><strong>Un grupo por cada curso de Phidias</strong><br>
        <span class="txt-sm txt-muted">El grupo del portal conserva el nombre del curso (K10C) y recibe todos sus estudiantes.</span></span>
    </label>
    <label class="check">
      <input type="radio" name="modo" value="unico" <?= $modo === 'unico' ? 'checked' : '' ?>>
      <span><strong>Un solo grupo con estudiantes de varios cursos</strong><br>
        <span class="txt-sm txt-muted">Le pones el nombre que quieras y eliges estudiantes de 10A, 10B, 10C… en el paso siguiente.</span></span>
    </label>
    <label class="check">
      <input type="radio" name="modo" value="existente" <?= $modo === 'existente' ? 'checked' : '' ?> <?= $misGrupos ? '' : 'disabled' ?>>
      <span><strong>Agregar a un grupo que ya existe</strong><br>
        <span class="txt-sm txt-muted"><?= $misGrupos ? 'Suma los estudiantes a uno de tus grupos actuales.' : 'Todavía no tienes grupos activos.' ?></span></span>
    </label>
  </div>

  <div class="panel">
    <div class="panel-h">
      <h2>Cursos de la matrícula</h2>
      <input type="search" data-filtra="#tabla-cursos" placeholder="Filtrar por curso o klasse" style="max-width:250px">
    </div>
    <div class="tabla-caja">
      <table class="tabla" id="tabla-cursos">
        <thead><tr><th style="width:40px">&nbsp;</th><th>Curso</th><th>Klasse</th><th>Etapa</th><th class="num">Estudiantes</th><th>Nivel del plan</th></tr></thead>
        <tbody>
        <?php foreach ($porKlasse as $grupoKlasse => $lista): ?>
          <?php foreach ($lista as $c): ?>
            <tr>
              <td><input type="checkbox" name="cursos[]" value="<?= h($c['curso']) ?>"></td>
              <td><strong><?= h($c['curso']) ?></strong></td>
              <td class="txt-sm"><?= h($c['klasse']) ?></td>
              <td class="txt-sm txt-muted"><?= h($c['etapa']) ?></td>
              <td class="num"><?= count($c['estudiantes']) ?></td>
              <td>
                <?php if ($c['nivel_id']): ?>
                  <span class="chip chip-verde"><?= h($c['grado']) ?>.º</span>
                <?php else: ?>
                  <span class="chip chip-gris">fuera del plan</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="form-acc">
    <button class="btn btn-lg" type="submit">Continuar</button>
    <a class="btn btn-ghost" href="<?= url('portal/docente/grupos.php') ?>">Cancelar</a>
  </div>
</form>
<?php endif; ?>

<div class="panel mt-3">
  <div class="panel-h"><h3>Cómo funciona</h3></div>
  <ul class="txt-sm txt-muted" style="padding-left:1rem">
    <li>La matrícula se lee de <code>GET /1/course/consolidate</code> y se guarda en caché tres horas.</li>
    <li>Las cuentas se identifican por el correo institucional y por el código del estudiante: si ya existen, se reutilizan y se actualizan sus datos.</li>
    <li>Las contraseñas nuevas se generan al azar y se descargan en CSV una sola vez, al terminar la importación.</li>
    <li>Al matricular a alguien en un grupo con actividades abiertas, sus entregas se crean automáticamente.</li>
    <li>Los estudiantes sin correo en Phidias no se pueden importar y aparecen listados al final.</li>
  </ul>
</div>
<?php pie(); ?>
