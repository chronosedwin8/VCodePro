<?php
/**
 * Ajustes generales del portal y mantenimiento.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/phidias.php';

$u = exigir_rol('admin');

$campos = [
    'colegio_principal' => ['Colegio principal', 'texto', 'Nombre que aparece en los informes y en el pie del portal.'],
    'anio_escolar'      => ['Año escolar', 'texto', 'Se usa como valor por defecto al crear grupos.'],
    'periodo_actual'    => ['Periodo actual', 'texto', 'Ej.: Periodo 3.'],
    'nota_aprobacion'   => ['Nota mínima de aprobación', 'texto', 'En la escala IB de 1 a 7.'],
    'contacto_soporte'  => ['Correo de soporte', 'texto', 'Se muestra a los clientes en su panel.'],
    'ia_global'         => ['Asistencia de IA habilitada', 'bool', 'Interruptor general; cada grupo puede desactivarla por su cuenta.'],
    'registro_abierto'  => ['Registro público abierto', 'bool', 'Permite que estudiantes y docentes creen su cuenta desde el sitio.'],
    'aviso_portal'      => ['Aviso para el portal', 'texto_largo', 'Mensaje visible en el panel de todos los usuarios. Déjalo vacío para ocultarlo.'],
];

if (es_post()) {
    exigir_csrf();
    $accion = post('accion');

    if ($accion === 'guardar') {
        foreach ($campos as $clave => [$etiqueta, $tipo]) {
            $valor = $tipo === 'bool' ? (isset($_POST[$clave]) ? '1' : '0') : post($clave);
            guardar_ajuste($clave, $valor);
        }
        auditar('ajustes_actualizados');
        flash_ok('Ajustes guardados.');
    }

    if ($accion === 'recalcular') {
        $n = 0;
        foreach (filas('SELECT id FROM entregas') as $e) { recalcular_progreso((int) $e['id']); $n++; }
        flash_ok("Se recalculó el avance de $n entrega(s).");
    }

    if ($accion === 'recalcular_notas') {
        $n = 0;
        foreach (filas('SELECT DISTINCT entrega_id FROM calificaciones') as $c) { calcular_nota((int) $c['entrega_id']); $n++; }
        flash_ok("Se recalcularon $n nota(s).");
    }

    if ($accion === 'insignias') {
        $n = 0;
        foreach (filas('SELECT id FROM usuarios WHERE rol = "estudiante"') as $x) { revisar_insignias((int) $x['id']); $n++; }
        flash_ok("Se revisaron las insignias de $n estudiante(s).");
    }

    if ($accion === 'phidias') {
        guardar_ajuste('phidias_url', post('phidias_url') ?: 'https://ds-barranquilla.phidias.co/rest');
        // El token solo se reemplaza si se escribe uno nuevo.
        if (post('phidias_token') !== '') guardar_ajuste('phidias_token', post('phidias_token'));
        auditar('phidias_configurada');
        flash_ok('Conexión con Phidias guardada.');
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'phidias_probar') {
        [$ok, $datos, $cuando] = array_pad(phidias_cursos(true), 3, null);
        if ($ok) {
            $n = array_sum(array_map(fn($c) => count($c['estudiantes']), $datos));
            flash_ok('Conexión correcta: ' . count($datos) . ' cursos y ' . $n . ' estudiantes matriculados.');
        } else {
            flash_err((string) $datos);
        }
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'limpiar_sesiones') {
        $n = q('DELETE FROM recordatorios_sesion WHERE expira_en < NOW()')->rowCount();
        $n += q('DELETE FROM recuperaciones WHERE expira_en < NOW() OR usado = 1')->rowCount();
        flash_ok("Se eliminaron $n token(s) vencidos.");
    }
    redirigir('portal/admin/ajustes.php');
}

$tablas = filas('SELECT TABLE_NAME AS tabla, TABLE_ROWS AS filas
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [DB_NOMBRE]);

$version = valor('SELECT VERSION()');

cabecera('Ajustes', [
    'titulo' => 'Ajustes del portal',
    'sub'    => 'Configuración institucional, mantenimiento y estado del sistema.',
    'migas'  => [['Panel', 'portal/admin/index.php'], ['Ajustes']],
]);
?>
<div class="rejilla rej-lat">
  <div>
    <form method="post" class="panel" data-avisar>
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="guardar">
      <div class="panel-h"><h2>Configuración institucional</h2></div>
      <?php foreach ($campos as $clave => [$etiqueta, $tipo, $pista]):
          $valor = (string) ajuste($clave, '');
      ?>
        <?php if ($tipo === 'bool'): ?>
          <label class="check">
            <input type="checkbox" name="<?= $clave ?>" value="1" <?= $valor === '1' ? 'checked' : '' ?>>
            <span><strong><?= h($etiqueta) ?></strong><br><span class="txt-sm txt-muted"><?= h($pista) ?></span></span>
          </label>
        <?php elseif ($tipo === 'texto_largo'): ?>
          <div class="campo">
            <label for="<?= $clave ?>"><?= h($etiqueta) ?></label>
            <textarea id="<?= $clave ?>" name="<?= $clave ?>" style="min-height:80px"><?= h($valor) ?></textarea>
            <span class="pista"><?= h($pista) ?></span>
          </div>
        <?php else: ?>
          <div class="campo">
            <label for="<?= $clave ?>"><?= h($etiqueta) ?></label>
            <input type="text" id="<?= $clave ?>" name="<?= $clave ?>" value="<?= h($valor) ?>">
            <span class="pista"><?= h($pista) ?></span>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="form-acc"><button class="btn" type="submit">Guardar ajustes</button></div>
    </form>

    <div class="panel panel-plano">
      <div class="panel-h"><h2>Contenido de la base de datos</h2><p><?= count($tablas) ?> tablas</p></div>
      <div class="tabla-caja">
        <table class="tabla tabla-mini">
          <thead><tr><th>Tabla</th><th class="num">Filas aproximadas</th></tr></thead>
          <tbody>
          <?php foreach ($tablas as $t): ?>
            <tr><td class="mono"><?= h($t['tabla']) ?></td><td class="num"><?= number_format((int) $t['filas'], 0, ',', '.') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <aside>
    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <div class="panel-h"><h3>Mantenimiento</h3></div>
      <button class="btn btn-ghost btn-block mb-2" name="accion" value="recalcular">Recalcular avance de entregas</button>
      <button class="btn btn-ghost btn-block mb-2" name="accion" value="recalcular_notas">Recalcular notas de la rúbrica</button>
      <button class="btn btn-ghost btn-block mb-2" name="accion" value="insignias">Revisar insignias de estudiantes</button>
      <button class="btn btn-ghost btn-block" name="accion" value="limpiar_sesiones">Limpiar tokens vencidos</button>
      <p class="txt-sm txt-muted mt-2 mb-0">Estas tareas son seguras: recalculan valores derivados sin modificar el trabajo de los estudiantes.</p>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="phidias">
      <div class="panel-h">
        <h3>Conexión con Phidias</h3>
        <?= phidias_configurada() ? '<span class="chip chip-verde">Configurada</span>' : '<span class="chip chip-gris">Sin token</span>' ?>
      </div>
      <div class="campo">
        <label for="phidias_url">URL base de la API</label>
        <input type="url" id="phidias_url" name="phidias_url" value="<?= h(phidias_url()) ?>">
      </div>
      <div class="campo">
        <label for="phidias_token">Token JWT</label>
        <input type="password" id="phidias_token" name="phidias_token" autocomplete="off"
               placeholder="<?= phidias_configurada() ? 'Guardado (' . h(phidias_token_pista()) . '). Escribe uno nuevo para reemplazarlo.' : 'Pega aquí el token' ?>">
        <span class="pista">Se guarda en la base de datos. Si prefieres dejarlo fuera del portal, defínelo en <code>includes/config.local.php</code>: esa constante tiene prioridad.</span>
      </div>
      <div class="btn-fila">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn btn-ghost" name="accion" value="phidias_probar">Probar conexión</button>
      </div>
      <p class="txt-sm txt-muted mt-2 mb-0">
        La importación de cursos y estudiantes se hace desde
        <a href="<?= url('portal/docente/importar.php') ?>">Importar de Phidias</a>.
      </p>
    </form>

    <div class="panel">
      <div class="panel-h"><h3>Estado del sistema</h3></div>
      <dl class="dl">
        <dt>PHP</dt><dd><?= h(PHP_VERSION) ?></dd>
        <dt>MySQL</dt><dd><?= h((string) $version) ?></dd>
        <dt>Base de datos</dt><dd class="mono"><?= h(DB_NOMBRE) ?></dd>
        <dt>Entorno</dt><dd><?= h(APP_ENTORNO) ?></dd>
        <dt>Ruta base</dt><dd class="mono"><?= h(BASE_URL === '' ? '/' : BASE_URL) ?></dd>
        <dt>Subidas</dt><dd><?= is_writable(RUTA_SUBIDAS) ? 'carpeta con permisos de escritura' : '<span class="chip chip-rojo">sin permisos de escritura</span>' ?></dd>
      </dl>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Seguridad</h3></div>
      <ul class="txt-sm txt-muted" style="padding-left:1rem">
        <li>Elimina <code>instalar.php</code> en producción.</li>
        <li>Cambia las contraseñas de demostración antes de abrir el portal.</li>
        <li>Sirve el sitio por HTTPS: las cookies se marcan como seguras automáticamente.</li>
        <li>Haz copia de seguridad de la base de datos antes de cada periodo.</li>
      </ul>
    </div>
  </aside>
</div>
<?php pie(); ?>
