<?php
/**
 * Creación de una actividad por parte de un docente, con ayuda del asistente.
 *
 * El docente escribe de qué quiere que trate, el asistente redacta el borrador
 * completo —incluidas las cuatro fases del ciclo de diseño— y él lo revisa,
 * lo ajusta y lo guarda. Lo que se guarda es lo que hay en el formulario: si
 * el asistente no está disponible, la página funciona igual, escribiendo a
 * mano.
 *
 * La actividad queda a nombre de quien la crea (`creado_por`) y nace **sin
 * publicar**: aparece en el banco cuando la coordinación la revisa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/ia.php';

$u = exigir_rol('docente', 'admin');
if (!ia_permitida($u)) {
    flash_err('La creación de actividades con asistente no está habilitada para tu cuenta.');
    redirigir('portal/docente/banco.php');
}

$niveles = filas('SELECT * FROM niveles ORDER BY grado');

// ------------------------------------------------------------- acciones --
if (es_post()) {
    exigir_csrf();

    $nivelId = post_int('nivel_id');
    $titulo  = post('titulo');
    $codigo  = strtoupper(trim(post('codigo')));

    if ($codigo === '') {
        // Código automático: VCP-<grado>-<consecutivo>.
        $grado = (int) (valor('SELECT grado FROM niveles WHERE id = ?', [$nivelId]) ?: 0);
        $n = (int) valor('SELECT COUNT(*) FROM actividades WHERE nivel_id = ?', [$nivelId], 0) + 1;
        do {
            $codigo = sprintf('VCP-%d-%02d', $grado, $n++);
        } while (valor('SELECT id FROM actividades WHERE codigo = ?', [$codigo]));
    }

    if ($titulo === '' || !$nivelId) {
        flash_err('El título y el nivel son obligatorios.');
    } elseif (valor('SELECT id FROM actividades WHERE codigo = ?', [$codigo])) {
        flash_err('Ya existe una actividad con el código ' . $codigo . '.');
    } else {
        $datos = [
            'nivel_id'            => $nivelId,
            'codigo'              => $codigo,
            'titulo'              => $titulo,
            'resumen'             => post('resumen'),
            'descripcion'         => post_rico('descripcion'),
            'pregunta_indagacion' => post('pregunta_indagacion') ?: null,
            'criterios_ib'        => in_array(valor('SELECT codigo FROM niveles WHERE id = ?', [$nivelId]), ['N11', 'N12'], true)
                                     ? 'A,B,C,D,E' : 'A,B,C,D',
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
            'orden'               => 0,
            // Nace sin publicar: la coordinación revisa antes de que llegue al banco.
            'publicada'           => 0,
            'creado_por'          => $u['id'],
        ];
        $id = insertar('actividades', $datos);

        require_once APP_RAIZ . '/db/seed/comun.php';
        $codNivel = (string) valor('SELECT codigo FROM niveles WHERE id = ?', [$nivelId], 'N6');
        foreach (rubrica_para($codNivel, 'la solución') as [$cr, $nom, $d12, $d34, $d56, $d78, $max]) {
            insertar('rubrica_criterios', [
                'actividad_id' => $id, 'criterio' => $cr, 'nombre' => $nom,
                'descriptor_12' => $d12, 'descriptor_34' => $d34,
                'descriptor_56' => $d56, 'descriptor_78' => $d78, 'maximo' => $max,
            ]);
        }

        // Las fases llegan del formulario (una por etapa) o, si vienen vacías,
        // se crean en blanco para completarlas después.
        $o = 0;
        foreach (FASES_CICLO as $clave => $nombreFase) {
            $o++;
            insertar('actividad_fases', [
                'actividad_id'  => $id,
                'fase'          => $clave,
                'titulo'        => post('fase_titulo_' . $clave) ?: $nombreFase,
                'instrucciones' => post('fase_instr_' . $clave) ?: 'Describe aquí lo que hará el estudiante en esta fase.',
                'entregable'    => post('fase_evid_' . $clave) ?: 'Evidencia esperada de la fase.',
                'minutos'       => max(5, min(600, post_int('fase_min_' . $clave) ?: 45)),
                'orden'         => $o,
            ]);
        }

        auditar('actividad_creada', 'actividades', $id, $codigo . ' · por docente');
        flash_ok('Actividad ' . $codigo . ' creada con su rúbrica y sus cuatro fases. '
               . 'Queda sin publicar hasta que la coordinación la revise; ya puedes asignarla a tus grupos desde el banco.');
        redirigir('portal/docente/actividad.php?id=' . $id);
    }
}

cabecera('Nueva actividad', [
    'titulo' => 'Nueva actividad',
    'sub'    => 'Redáctala con el asistente y ajústala a tu grupo.',
    'migas'  => [['Panel', 'portal/docente/index.php'], ['Banco', 'portal/docente/banco.php'], ['Nueva']],
]);
?>

<form method="post" data-avisar>
  <?= csrf_campo() ?>

  <div class="panel">
    <div class="panel-h">
      <h2>Redactar con el asistente</h2>
      <span class="ia-marca">IA</span>
    </div>
    <div class="campo">
      <label for="tema">¿De qué quieres que trate?</label>
      <input type="text" id="tema" data-ia-tema
             placeholder="Ej.: un semáforo con micro:bit que mida el tiempo de cruce de la calle del colegio">
      <span class="pista">Cuanto más concreto seas —el producto, el contexto, la restricción— mejor sale el borrador.</span>
    </div>
    <div class="campo-fila">
      <div class="campo">
        <label for="nivel_id">Nivel</label>
        <select id="nivel_id" name="nivel_id" data-ia-nivel required>
          <?php foreach ($niveles as $n): ?>
            <option value="<?= (int) $n['id'] ?>"><?= (int) $n['grado'] ?>.º — <?= h($n['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="codigo">Código</label>
        <input type="text" id="codigo" name="codigo" maxlength="20" placeholder="Se genera solo si lo dejas vacío">
      </div>
    </div>
    <div class="form-acc">
      <button class="btn" type="button" data-ia-redactar
              data-url="<?= url('portal/api/ia_actividad.php') ?>"
              data-csrf="<?= h(csrf_token()) ?>">Redactar borrador</button>
      <span class="pista" data-ia-redaccion-estado role="status"></span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-h"><h2>La actividad</h2><p>Revisa y ajusta antes de guardar</p></div>

    <div class="campo">
      <label for="titulo">Título</label>
      <input type="text" id="titulo" name="titulo" maxlength="200" required>
    </div>
    <div class="campo">
      <label for="resumen">Resumen</label>
      <input type="text" id="resumen" name="resumen" maxlength="300">
    </div>
    <div class="campo">
      <label for="descripcion">Descripción</label>
      <textarea id="descripcion" name="descripcion" data-rico style="min-height:150px"></textarea>
      <span class="pista">Es lo primero que lee el estudiante al abrir la actividad.</span>
    </div>
    <div class="campo">
      <label for="pregunta_indagacion">Pregunta de indagación</label>
      <input type="text" id="pregunta_indagacion" name="pregunta_indagacion" maxlength="300">
    </div>

    <div class="campo-fila">
      <div class="campo">
        <label for="contexto_global">Contexto global</label>
        <select id="contexto_global" name="contexto_global">
          <?php foreach (CONTEXTOS_GLOBALES as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="concepto_clave">Concepto clave</label>
        <input type="text" id="concepto_clave" name="concepto_clave" maxlength="120">
      </div>
    </div>
    <div class="campo-fila">
      <div class="campo">
        <label for="perfil_ib">Atributo del perfil</label>
        <select id="perfil_ib" name="perfil_ib">
          <?php foreach (PERFIL_IB as $p): ?><option value="<?= h($p) ?>"><?= h($p) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="atl">Habilidad ATL</label>
        <select id="atl" name="atl">
          <?php foreach (ATL as $a): ?><option value="<?= h($a) ?>"><?= h($a) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="campo-fila">
      <div class="campo">
        <label for="objetivos">Objetivos <span class="txt-sm txt-muted">(uno por línea)</span></label>
        <textarea id="objetivos" name="objetivos" style="min-height:110px"></textarea>
      </div>
      <div class="campo">
        <label for="entregables">Entregables <span class="txt-sm txt-muted">(uno por línea)</span></label>
        <textarea id="entregables" name="entregables" style="min-height:110px"></textarea>
      </div>
    </div>

    <div class="campo-fila-3">
      <div class="campo">
        <label for="lenguaje">Lenguaje o herramienta</label>
        <input type="text" id="lenguaje" name="lenguaje" value="Python" maxlength="60">
      </div>
      <div class="campo">
        <label for="dificultad">Dificultad</label>
        <select id="dificultad" name="dificultad">
          <option value="inicial">Inicial</option>
          <option value="intermedio">Intermedio</option>
          <option value="avanzado">Avanzado</option>
        </select>
      </div>
      <div class="campo">
        <label for="sesiones">Sesiones</label>
        <input type="number" id="sesiones" name="sesiones" value="4" min="1" max="40">
      </div>
      <div class="campo">
        <label for="horas">Horas</label>
        <input type="number" id="horas" name="horas" value="4" min="0.5" max="80" step="0.5">
      </div>
    </div>

    <div class="campo">
      <label for="codigo_inicial">Código de arranque <span class="txt-sm txt-muted">(opcional)</span></label>
      <textarea id="codigo_inicial" name="codigo_inicial" style="min-height:90px;font-family:ui-monospace,Consolas,monospace"></textarea>
    </div>
    <label class="check">
      <input type="checkbox" name="ia_sugerida" value="1">
      <span>La actividad admite el uso de asistentes de IA por parte del estudiante</span>
    </label>
  </div>

  <div class="panel">
    <div class="panel-h"><h2>Fases del ciclo de diseño</h2><p>Las cuatro etapas del PAI</p></div>
    <?php foreach (FASES_CICLO as $clave => $nombreFase): ?>
      <div class="ia-caja">
        <h4><?= h($nombreFase) ?></h4>
        <div class="campo-fila">
          <div class="campo">
            <label for="ft_<?= $clave ?>">Título de la fase</label>
            <input type="text" id="ft_<?= $clave ?>" name="fase_titulo_<?= $clave ?>" maxlength="200">
          </div>
          <div class="campo">
            <label for="fm_<?= $clave ?>">Minutos</label>
            <input type="number" id="fm_<?= $clave ?>" name="fase_min_<?= $clave ?>" value="45" min="5" max="600">
          </div>
        </div>
        <div class="campo">
          <label for="fi_<?= $clave ?>">Instrucciones</label>
          <textarea id="fi_<?= $clave ?>" name="fase_instr_<?= $clave ?>" data-rico style="min-height:90px"></textarea>
        </div>
        <div class="campo mb-0">
          <label for="fe_<?= $clave ?>">Evidencia esperada</label>
          <input type="text" id="fe_<?= $clave ?>" name="fase_evid_<?= $clave ?>" maxlength="300">
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="form-acc">
    <button class="btn btn-lg" type="submit">Crear actividad</button>
    <a class="btn btn-ghost" href="<?= url('portal/docente/banco.php') ?>">Cancelar</a>
  </div>
</form>
<?php pie(); ?>
