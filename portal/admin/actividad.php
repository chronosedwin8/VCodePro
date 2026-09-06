<?php
/**
 * Editor de una actividad: datos generales, fases del ciclo, rúbrica y recursos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('admin');
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$niveles = filas('SELECT id, codigo, grado, nombre FROM niveles ORDER BY orden');

// ------------------------------------------------------------- acciones ---
if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    // --- datos generales ---------------------------------------------------
    if ($accion === 'guardar') {
        $datos = [
            'nivel_id'            => post_int('nivel_id'),
            'codigo'              => post('codigo'),
            'titulo'              => post('titulo'),
            'resumen'             => post('resumen'),
            'descripcion'         => post_rico('descripcion'),
            'pregunta_indagacion' => post('pregunta_indagacion') ?: null,
            'criterios_ib'        => post('criterios_ib') ?: 'A,B,C,D',
            'contexto_global'     => post('contexto_global'),
            'concepto_clave'      => post('concepto_clave') ?: null,
            'perfil_ib'           => post('perfil_ib') ?: null,
            'atl'                 => post('atl') ?: null,
            'objetivos'           => post('objetivos') ?: null,
            'entregables'         => post('entregables') ?: null,
            'lenguaje'            => post('lenguaje') ?: 'Python',
            'dificultad'          => in_array(post('dificultad'), ['inicial', 'intermedio', 'avanzado'], true) ? post('dificultad') : 'inicial',
            'sesiones'            => max(1, post_int('sesiones')),
            'horas'               => (float) post('horas', '4'),
            'ia_sugerida'         => isset($_POST['ia_sugerida']) ? 1 : 0,
            'codigo_inicial'      => post('codigo_inicial') ?: null,
            'orden'               => post_int('orden'),
            'publicada'           => isset($_POST['publicada']) ? 1 : 0,
        ];
        if ($datos['codigo'] === '' || $datos['titulo'] === '' || !$datos['nivel_id']) {
            flash_err('Código, título y nivel son obligatorios.');
            redirigir('portal/admin/actividad.php?id=' . $id);
        }
        $dup = valor('SELECT id FROM actividades WHERE codigo = ? AND id <> ?', [$datos['codigo'], $id]);
        if ($dup) {
            flash_err('Ya existe otra actividad con el código ' . $datos['codigo'] . '.');
            redirigir('portal/admin/actividad.php?id=' . $id);
        }

        if ($id) {
            actualizar('actividades', $datos, 'id = :__id', ['__id' => $id]);
            auditar('actividad_editada', 'actividades', $id, $datos['codigo']);
            flash_ok('Actividad actualizada.');
        } else {
            $datos['creado_por'] = $u['id'];
            $id = insertar('actividades', $datos);
            // Rúbrica base según el programa del nivel.
            require_once APP_RAIZ . '/db/seed/comun.php';
            $codNivel = (string) valor('SELECT codigo FROM niveles WHERE id = ?', [$datos['nivel_id']], 'N6');
            foreach (rubrica_para($codNivel, 'la solución') as [$cr, $nom, $d12, $d34, $d56, $d78, $max]) {
                insertar('rubrica_criterios', [
                    'actividad_id' => $id, 'criterio' => $cr, 'nombre' => $nom,
                    'descriptor_12' => $d12, 'descriptor_34' => $d34,
                    'descriptor_56' => $d56, 'descriptor_78' => $d78, 'maximo' => $max,
                ]);
            }
            // Cuatro fases del ciclo de diseño listas para completar.
            $o = 0;
            foreach (FASES_CICLO as $clave => $nombreFase) {
                insertar('actividad_fases', [
                    'actividad_id' => $id, 'fase' => $clave, 'titulo' => $nombreFase,
                    'instrucciones' => 'Describe aquí lo que hará el estudiante en esta fase.',
                    'entregable' => 'Evidencia esperada de la fase.', 'minutos' => 45, 'orden' => ++$o,
                ]);
            }
            auditar('actividad_creada', 'actividades', $id, $datos['codigo']);
            flash_ok('Actividad creada con su rúbrica y sus cuatro fases base. Ya puedes ajustarlas.');
        }
        redirigir('portal/admin/actividad.php?id=' . $id);
    }

    // --- fases -------------------------------------------------------------
    if ($accion === 'fase_guardar' && $id) {
        $fid = post_int('fase_id');
        $datos = [
            'fase'          => in_array(post('fase'), array_keys(FASES_CICLO), true) ? post('fase') : 'indagar',
            'titulo'        => post('titulo'),
            'instrucciones' => post_rico('instrucciones'),
            'entregable'    => post('entregable'),
            'minutos'       => post_int('minutos'),
            'orden'         => post_int('orden'),
        ];
        if ($fid) {
            actualizar('actividad_fases', $datos, 'id = :__id AND actividad_id = :__a', ['__id' => $fid, '__a' => $id]);
            flash_ok('Fase actualizada.');
        } else {
            $datos['actividad_id'] = $id;
            insertar('actividad_fases', $datos);
            flash_ok('Fase agregada.');
        }
        redirigir('portal/admin/actividad.php?id=' . $id . '#fases');
    }

    if ($accion === 'fase_borrar' && $id) {
        borrar('actividad_fases', 'id = ? AND actividad_id = ?', [post_int('fase_id'), $id]);
        flash_ok('Fase eliminada.');
        redirigir('portal/admin/actividad.php?id=' . $id . '#fases');
    }

    // --- rúbrica -----------------------------------------------------------
    if ($accion === 'criterio_guardar' && $id) {
        $cid = post_int('criterio_id');
        $datos = [
            'criterio'      => mb_strtoupper(mb_substr(post('criterio'), 0, 1)),
            'nombre'        => post('nombre'),
            'descriptor_12' => post('d12'),
            'descriptor_34' => post('d34'),
            'descriptor_56' => post('d56'),
            'descriptor_78' => post('d78'),
            'maximo'        => max(1, post_int('maximo')),
        ];
        if ($cid) {
            actualizar('rubrica_criterios', $datos, 'id = :__id AND actividad_id = :__a', ['__id' => $cid, '__a' => $id]);
            flash_ok('Criterio actualizado.');
        } else {
            $datos['actividad_id'] = $id;
            insertar('rubrica_criterios', $datos);
            flash_ok('Criterio agregado.');
        }
        redirigir('portal/admin/actividad.php?id=' . $id . '#rubrica');
    }

    if ($accion === 'criterio_borrar' && $id) {
        borrar('rubrica_criterios', 'id = ? AND actividad_id = ?', [post_int('criterio_id'), $id]);
        flash_ok('Criterio eliminado.');
        redirigir('portal/admin/actividad.php?id=' . $id . '#rubrica');
    }

    // --- recursos ----------------------------------------------------------
    if ($accion === 'recurso_agregar' && $id) {
        insertar('actividad_recursos', [
            'actividad_id' => $id,
            'tipo'         => in_array(post('tipo'), ['lectura', 'video', 'plantilla', 'dataset', 'enlace', 'codigo'], true) ? post('tipo') : 'enlace',
            'titulo'       => post('titulo'),
            'url'          => post('url') ?: null,
            'detalle'      => post('detalle') ?: null,
        ]);
        flash_ok('Recurso agregado.');
        redirigir('portal/admin/actividad.php?id=' . $id . '#recursos');
    }

    if ($accion === 'recurso_borrar' && $id) {
        borrar('actividad_recursos', 'id = ? AND actividad_id = ?', [post_int('recurso_id'), $id]);
        flash_ok('Recurso eliminado.');
        redirigir('portal/admin/actividad.php?id=' . $id . '#recursos');
    }
}

// ---------------------------------------------------------------- datos ---
$a = $id ? actividad_completa($id) : null;
if ($id && !$a) { flash_err('Actividad no encontrada.'); redirigir('portal/admin/actividades.php'); }

$usos = $id ? filas('SELECT s.id, g.nombre, s.fecha_entrega FROM asignaciones s JOIN grupos g ON g.id = s.grupo_id WHERE s.actividad_id = ?', [$id]) : [];

cabecera($a ? 'Editar actividad' : 'Nueva actividad', [
    'titulo' => $a ? $a['titulo'] : 'Nueva actividad',
    'sub'    => $a ? $a['codigo'] . ' · ' . $a['grado'] . ' · ' . $a['nivel_nombre'] : 'Define los datos generales; la rúbrica y las cuatro fases se crean automáticamente.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Actividades', 'portal/admin/actividades.php'], [$a['codigo'] ?? 'Nueva']],
    'acciones' => $a
        ? '<a class="btn btn-ghost" href="' . url('portal/docente/actividad.php?id=' . $id) . '">Ver como docente</a>'
        : '',
]);
?>
<form method="post" class="panel" data-avisar>
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="guardar">
  <div class="panel-h"><h2>Datos generales</h2></div>

  <div class="campo-fila-3">
    <div class="campo">
      <label for="codigo">Código</label>
      <input type="text" id="codigo" name="codigo" value="<?= h($a['codigo'] ?? '') ?>" required placeholder="VCP-9-11">
    </div>
    <div class="campo">
      <label for="nivel_id">Nivel</label>
      <select id="nivel_id" name="nivel_id" required>
        <?php foreach ($niveles as $n): ?>
          <option value="<?= (int) $n['id'] ?>" <?= (int) ($a['nivel_id'] ?? 0) === (int) $n['id'] ? 'selected' : '' ?>>
            <?= h($n['grado'] . ' · ' . $n['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="orden">Orden dentro del nivel</label>
      <input type="number" id="orden" name="orden" value="<?= (int) ($a['orden'] ?? 0) ?>" min="0" max="99">
    </div>
  </div>

  <div class="campo">
    <label for="titulo">Título</label>
    <input type="text" id="titulo" name="titulo" value="<?= h($a['titulo'] ?? '') ?>" required>
  </div>
  <div class="campo">
    <label for="resumen">Resumen</label>
    <input type="text" id="resumen" name="resumen" value="<?= h($a['resumen'] ?? '') ?>" maxlength="400" required>
    <span class="pista">Una línea: qué hace el estudiante y qué produce.</span>
  </div>
  <div class="campo">
    <label for="descripcion">Descripción</label>
    <textarea id="descripcion" name="descripcion" data-rico style="min-height:160px"><?= h($a['descripcion'] ?? '') ?></textarea>
    <span class="pista">Es lo primero que lee el estudiante al abrir la actividad. Da contexto y di qué va a construir.</span>
  </div>
  <div class="campo">
    <label for="pregunta_indagacion">Pregunta de indagación</label>
    <input type="text" id="pregunta_indagacion" name="pregunta_indagacion" value="<?= h($a['pregunta_indagacion'] ?? '') ?>" maxlength="400">
  </div>

  <div class="campo-fila-3">
    <div class="campo">
      <label for="contexto_global">Contexto global</label>
      <select id="contexto_global" name="contexto_global">
        <?php foreach (CONTEXTOS_GLOBALES as $c): ?>
          <option value="<?= h($c) ?>" <?= ($a['contexto_global'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
        <?php if (!empty($a['contexto_global']) && !in_array($a['contexto_global'], CONTEXTOS_GLOBALES, true)): ?>
          <option value="<?= h($a['contexto_global']) ?>" selected><?= h($a['contexto_global']) ?></option>
        <?php endif; ?>
      </select>
    </div>
    <div class="campo">
      <label for="concepto_clave">Concepto clave</label>
      <input type="text" id="concepto_clave" name="concepto_clave" value="<?= h($a['concepto_clave'] ?? '') ?>">
    </div>
    <div class="campo">
      <label for="criterios_ib">Criterios evaluados</label>
      <input type="text" id="criterios_ib" name="criterios_ib" value="<?= h($a['criterios_ib'] ?? 'A,B,C,D') ?>">
    </div>
  </div>

  <div class="campo-fila">
    <div class="campo">
      <label for="perfil_ib">Perfil de la comunidad</label>
      <input type="text" id="perfil_ib" name="perfil_ib" value="<?= h($a['perfil_ib'] ?? '') ?>" placeholder="Indagador|Pensador">
      <span class="pista">Separa con barra vertical.</span>
    </div>
    <div class="campo">
      <label for="atl">Enfoques del aprendizaje</label>
      <input type="text" id="atl" name="atl" value="<?= h($a['atl'] ?? '') ?>" placeholder="Organización|Colaboración">
    </div>
  </div>

  <div class="campo-fila">
    <div class="campo">
      <label for="objetivos">Objetivos de aprendizaje</label>
      <textarea id="objetivos" name="objetivos" style="min-height:100px"><?= h($a['objetivos'] ?? '') ?></textarea>
      <span class="pista">Uno por elemento, separados con barra vertical.</span>
    </div>
    <div class="campo">
      <label for="entregables">Entregables</label>
      <textarea id="entregables" name="entregables" style="min-height:100px"><?= h($a['entregables'] ?? '') ?></textarea>
      <span class="pista">Separados con barra vertical.</span>
    </div>
  </div>

  <div class="campo-fila-3">
    <div class="campo">
      <label for="lenguaje">Lenguaje o herramienta</label>
      <input type="text" id="lenguaje" name="lenguaje" value="<?= h($a['lenguaje'] ?? 'Python') ?>">
    </div>
    <div class="campo">
      <label for="dificultad">Dificultad</label>
      <select id="dificultad" name="dificultad">
        <?php foreach (['inicial', 'intermedio', 'avanzado'] as $d): ?>
          <option value="<?= $d ?>" <?= ($a['dificultad'] ?? 'inicial') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="sesiones">Sesiones y horas</label>
      <div class="btn-fila">
        <input type="number" id="sesiones" name="sesiones" value="<?= (int) ($a['sesiones'] ?? 4) ?>" min="1" max="20">
        <input type="number" name="horas" value="<?= (float) ($a['horas'] ?? 4) ?>" min="0.5" max="40" step="0.5">
      </div>
    </div>
  </div>

  <div class="campo">
    <label for="codigo_inicial">Código de inicio para el estudiante</label>
    <textarea id="codigo_inicial" name="codigo_inicial" style="min-height:140px;font-family:var(--mono);font-size:.86rem"><?= h($a['codigo_inicial'] ?? '') ?></textarea>
  </div>

  <label class="check"><input type="checkbox" name="ia_sugerida" value="1" <?= ($a['ia_sugerida'] ?? 1) ? 'checked' : '' ?>><span>Sugerir asistencia de IA en modo pedagógico</span></label>
  <label class="check"><input type="checkbox" name="publicada" value="1" <?= ($a['publicada'] ?? 1) ? 'checked' : '' ?>><span>Publicada en el banco (visible para los docentes)</span></label>

  <div class="form-acc">
    <button class="btn" type="submit"><?= $a ? 'Guardar cambios' : 'Crear actividad' ?></button>
    <a class="btn btn-ghost" href="<?= url('portal/admin/actividades.php') ?>">Volver al banco</a>
  </div>
</form>

<?php if ($a): ?>

<!-- ================================= FASES ================================ -->
<div class="panel" id="fases">
  <div class="panel-h">
    <h2>Fases del ciclo de diseño</h2>
    <p><?= count($a['fases']) ?> fase(s) · <?= array_sum(array_map(fn($f) => (int) $f['minutos'], $a['fases'])) ?> minutos</p>
  </div>

  <?php foreach ($a['fases'] as $f): ?>
    <form method="post" class="fase" style="margin-bottom:14px;padding:16px">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="fase_guardar">
      <input type="hidden" name="fase_id" value="<?= (int) $f['id'] ?>">
      <div class="campo-fila-3">
        <div class="campo">
          <label>Fase</label>
          <select name="fase">
            <?php foreach (FASES_CICLO as $k => $v): ?>
              <option value="<?= $k ?>" <?= $f['fase'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo"><label>Minutos</label><input type="number" name="minutos" value="<?= (int) $f['minutos'] ?>" min="5" max="600"></div>
        <div class="campo"><label>Orden</label><input type="number" name="orden" value="<?= (int) $f['orden'] ?>" min="0" max="99"></div>
      </div>
      <div class="campo"><label>Título de la fase</label><input type="text" name="titulo" value="<?= h($f['titulo']) ?>" required></div>
      <div class="campo"><label>Instrucciones</label><textarea name="instrucciones" data-rico style="min-height:100px"><?= h($f['instrucciones']) ?></textarea></div>
      <div class="campo"><label>Evidencia esperada</label><input type="text" name="entregable" value="<?= h($f['entregable']) ?>" maxlength="300"></div>
      <div class="btn-fila">
        <button class="btn btn-sm" type="submit">Guardar fase</button>
        <button class="btn btn-sm btn-err" name="accion" value="fase_borrar" data-confirmar="¿Eliminar esta fase? Se perderá el trabajo que los estudiantes hayan escrito en ella.">Eliminar</button>
      </div>
    </form>
  <?php endforeach; ?>

  <details class="fase">
    <summary><span class="fase-n">+</span><span>Agregar una fase</span></summary>
    <div class="fase-cuerpo">
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="fase_guardar">
        <div class="campo-fila-3">
          <div class="campo">
            <label>Fase</label>
            <select name="fase"><?php foreach (FASES_CICLO as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
          </div>
          <div class="campo"><label>Minutos</label><input type="number" name="minutos" value="45" min="5" max="600"></div>
          <div class="campo"><label>Orden</label><input type="number" name="orden" value="<?= count($a['fases']) + 1 ?>" min="0" max="99"></div>
        </div>
        <div class="campo"><label>Título</label><input type="text" name="titulo" required></div>
        <div class="campo"><label>Instrucciones</label><textarea name="instrucciones" data-rico style="min-height:90px"></textarea></div>
        <div class="campo"><label>Evidencia esperada</label><input type="text" name="entregable" maxlength="300"></div>
        <button class="btn btn-sm" type="submit">Agregar fase</button>
      </form>
    </div>
  </details>
</div>

<!-- ================================ RÚBRICA =============================== -->
<div class="panel" id="rubrica">
  <div class="panel-h">
    <h2>Rúbrica</h2>
    <p>Total <?= array_sum(array_map(fn($c) => (int) $c['maximo'], $a['rubrica'])) ?> puntos</p>
  </div>

  <?php foreach ($a['rubrica'] as $c): ?>
    <form method="post" class="rub-crit" style="margin-bottom:14px">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="criterio_guardar">
      <input type="hidden" name="criterio_id" value="<?= (int) $c['id'] ?>">
      <div class="campo-fila-3">
        <div class="campo"><label>Letra</label><input type="text" name="criterio" value="<?= h($c['criterio']) ?>" maxlength="1" required></div>
        <div class="campo"><label>Nombre</label><input type="text" name="nombre" value="<?= h($c['nombre']) ?>" required></div>
        <div class="campo"><label>Puntaje máximo</label><input type="number" name="maximo" value="<?= (int) $c['maximo'] ?>" min="1" max="20"></div>
      </div>
      <div class="campo-fila">
        <div class="campo"><label>Descriptor 1–2</label><textarea name="d12" style="min-height:70px"><?= h($c['descriptor_12']) ?></textarea></div>
        <div class="campo"><label>Descriptor 3–4</label><textarea name="d34" style="min-height:70px"><?= h($c['descriptor_34']) ?></textarea></div>
      </div>
      <div class="campo-fila">
        <div class="campo"><label>Descriptor 5–6</label><textarea name="d56" style="min-height:70px"><?= h($c['descriptor_56']) ?></textarea></div>
        <div class="campo"><label>Descriptor 7–8</label><textarea name="d78" style="min-height:70px"><?= h($c['descriptor_78']) ?></textarea></div>
      </div>
      <div class="btn-fila">
        <button class="btn btn-sm" type="submit">Guardar criterio</button>
        <button class="btn btn-sm btn-err" name="accion" value="criterio_borrar" data-confirmar="¿Eliminar el criterio y las calificaciones asociadas?">Eliminar</button>
      </div>
    </form>
  <?php endforeach; ?>

  <details class="fase">
    <summary><span class="fase-n">+</span><span>Agregar un criterio</span></summary>
    <div class="fase-cuerpo">
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="criterio_guardar">
        <div class="campo-fila-3">
          <div class="campo"><label>Letra</label><input type="text" name="criterio" maxlength="1" required></div>
          <div class="campo"><label>Nombre</label><input type="text" name="nombre" required></div>
          <div class="campo"><label>Máximo</label><input type="number" name="maximo" value="8" min="1" max="20"></div>
        </div>
        <div class="campo-fila">
          <div class="campo"><label>Descriptor 1–2</label><textarea name="d12" style="min-height:70px"></textarea></div>
          <div class="campo"><label>Descriptor 3–4</label><textarea name="d34" style="min-height:70px"></textarea></div>
        </div>
        <div class="campo-fila">
          <div class="campo"><label>Descriptor 5–6</label><textarea name="d56" style="min-height:70px"></textarea></div>
          <div class="campo"><label>Descriptor 7–8</label><textarea name="d78" style="min-height:70px"></textarea></div>
        </div>
        <button class="btn btn-sm" type="submit">Agregar criterio</button>
      </form>
    </div>
  </details>
</div>

<!-- ================================ RECURSOS ============================== -->
<div class="rejilla rej-lat" id="recursos">
  <div class="panel panel-plano">
    <div class="panel-h"><h2>Recursos</h2></div>
    <?php if (!$a['recursos']): ?>
      <div style="padding:20px"><p class="txt-muted mb-0">Sin recursos asociados.</p></div>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Recurso</th><th>Tipo</th><th>Detalle</th><th class="acc">&nbsp;</th></tr></thead>
          <tbody>
          <?php foreach ($a['recursos'] as $r): ?>
            <tr>
              <td><strong><?= h($r['titulo']) ?></strong><?= $r['url'] ? '<br><a class="txt-sm" href="' . h($r['url']) . '" target="_blank" rel="noopener">abrir</a>' : '' ?></td>
              <td><span class="chip chip-gris"><?= h($r['tipo']) ?></span></td>
              <td class="txt-sm txt-muted"><?= h($r['detalle']) ?></td>
              <td class="acc">
                <form method="post">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="accion" value="recurso_borrar">
                  <input type="hidden" name="recurso_id" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-xs btn-err" data-confirmar="¿Eliminar el recurso?">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="recurso_agregar">
      <div class="panel-h"><h3>Nuevo recurso</h3></div>
      <div class="campo"><label for="rtitulo">Título</label><input type="text" id="rtitulo" name="titulo" required></div>
      <div class="campo">
        <label for="rtipo">Tipo</label>
        <select id="rtipo" name="tipo">
          <?php foreach (['lectura', 'video', 'plantilla', 'dataset', 'enlace', 'codigo'] as $t): ?>
            <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="rurl">Enlace</label><input type="url" id="rurl" name="url" placeholder="https://"></div>
      <div class="campo"><label for="rdetalle">Detalle</label><textarea id="rdetalle" name="detalle" style="min-height:80px"></textarea></div>
      <button class="btn btn-block" type="submit">Agregar recurso</button>
    </form>

    <?php if ($usos): ?>
    <div class="panel">
      <div class="panel-h"><h3>Grupos que la usan</h3></div>
      <ul class="txt-sm" style="padding-left:1rem">
        <?php foreach ($usos as $x): ?>
          <li><a href="<?= url('portal/docente/asignaciones.php?id=' . (int) $x['id']) ?>"><?= h($x['nombre']) ?></a> · entrega <?= fecha($x['fecha_entrega']) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="txt-sm txt-muted mb-0">Los cambios en las fases afectan a estos grupos.</p>
    </div>
    <?php endif; ?>
  </aside>
</div>

<?php endif; ?>
<?php pie(); ?>
