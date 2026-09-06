<?php
/**
 * Ajustes generales del portal y mantenimiento.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';
require_once __DIR__ . '/../../includes/phidias.php';
require_once __DIR__ . '/../../includes/pagos_api.php';
require_once __DIR__ . '/../../includes/adjuntos.php';
require_once __DIR__ . '/../../includes/ia.php';
require_once __DIR__ . '/../../includes/sso.php';

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

    if ($accion === 'almacenamiento') {
        foreach (['s3_bucket', 's3_region', 's3_prefijo'] as $clave) {
            guardar_ajuste($clave, post($clave));
        }
        // Las llaves solo se reemplazan si se escriben nuevas.
        foreach (['s3_llave', 's3_secreto'] as $clave) {
            if (post($clave) !== '') guardar_ajuste($clave, post($clave));
        }
        auditar('almacenamiento_configurado');
        flash_ok('Almacenamiento de adjuntos guardado.');
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'microsoft') {
        guardar_ajuste('entra_cliente',  post('entra_cliente'));
        guardar_ajuste('entra_tenant',   post('entra_tenant'));
        guardar_ajuste('entra_dominios', post('entra_dominios'));
        guardar_ajuste('entra_alta',     isset($_POST['entra_alta']) ? '1' : '0');
        guardar_ajuste('entra_rol',      array_key_exists(post('entra_rol'), ROLES) && post('entra_rol') !== 'admin'
                                         ? post('entra_rol') : 'estudiante');
        // El secreto solo se reemplaza si se escribe uno nuevo.
        if (post('entra_secreto') !== '') guardar_ajuste('entra_secreto', post('entra_secreto'));
        auditar('sso_configurado');
        flash_ok('Ingreso con Microsoft guardado.');
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'asistente') {
        guardar_ajuste('ia_modelo', array_key_exists(post('ia_modelo'), IA_MODELOS) ? post('ia_modelo') : IA_MODELO_POR_DEFECTO);
        if (post('ia_clave') !== '') guardar_ajuste('ia_clave', post('ia_clave'));
        auditar('ia_configurada');
        flash_ok('Asistente de IA guardado.');
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'asistente_probar') {
        [$ok, $msg] = ia_probar();
        $ok ? flash_ok($msg) : flash_err($msg);
        redirigir('portal/admin/ajustes.php');
    }

    if ($accion === 'almacenamiento_probar') {
        [$ok, $msg] = s3_probar();
        $ok ? flash_ok($msg) : flash_err($msg);
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

    if ($accion === 'pagos') {
        // Cada credencial solo se reemplaza si se escribe una nueva.
        foreach (['mp_public_key', 'mp_access_token', 'mp_webhook_secret'] as $clave) {
            if (post($clave) !== '') guardar_ajuste($clave, post($clave));
        }
        guardar_ajuste('mp_modo', post('mp_modo') === 'produccion' ? 'produccion' : 'prueba');
        auditar('pagos_configurados');
        flash_ok('Credenciales de la pasarela guardadas.');
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
      <input type="hidden" name="accion" value="pagos">
      <div class="panel-h">
        <h3>Pasarela de pagos</h3>
        <?php if (!mp_configurado()): ?><span class="chip chip-gris">Sin credenciales</span>
        <?php elseif (mp_entorno() === 'prueba'): ?><span class="chip chip-ambar">Modo prueba</span>
        <?php else: ?><span class="chip chip-verde">Producción</span><?php endif; ?>
      </div>

      <p class="campo-label">URL del webhook</p>
      <p class="txt-sm mb-2">
        <code class="mono copiar" data-copiar="<?= h(mp_url_webhook()) ?>"
              style="display:block;padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-alt);word-break:break-all"><?= h(mp_url_webhook()) ?></code>
        <span class="pista">Pégala en Mercado Pago → Webhooks. Al guardarla, el panel genera la clave secreta que va abajo.</span>
      </p>

      <div class="campo">
        <label for="mp_modo">Modo</label>
        <select id="mp_modo" name="mp_modo">
          <option value="prueba" <?= mp_entorno() !== 'produccion' ? 'selected' : '' ?>>Prueba · no se cobra dinero real</option>
          <option value="produccion" <?= mp_entorno() === 'produccion' ? 'selected' : '' ?>>Producción · cobros reales</option>
        </select>
        <span class="pista">Debe coincidir con el juego de credenciales que pegues abajo.</span>
      </div>
      <div class="campo">
        <label for="mp_public_key">Public Key <span class="txt-sm txt-muted">(opcional)</span></label>
        <input type="text" id="mp_public_key" name="mp_public_key" autocomplete="off"
               placeholder="<?= mp_public_key() !== '' ? 'Guardada (' . h(mp_pista(mp_public_key())) . ')' : 'APP_USR-…' ?>">
        <span class="pista">Con Checkout Pro no hace falta: el portal no sirve ningún formulario de tarjeta.</span>
      </div>
      <div class="campo">
        <label for="mp_access_token">Access Token</label>
        <input type="password" id="mp_access_token" name="mp_access_token" autocomplete="off"
               placeholder="<?= mp_access_token() !== '' ? 'Guardado (' . h(mp_pista(mp_access_token())) . ')' : 'APP_USR-… o TEST-…' ?>">
      </div>
      <div class="campo">
        <label for="mp_webhook_secret">Clave secreta del webhook</label>
        <input type="password" id="mp_webhook_secret" name="mp_webhook_secret" autocomplete="off"
               placeholder="<?= mp_webhook_secret() !== '' ? 'Guardada (' . h(mp_pista(mp_webhook_secret())) . ')' : 'La genera Mercado Pago al registrar la URL' ?>">
        <span class="pista">Sin ella, el portal acepta las notificaciones pero no puede comprobar que vengan de Mercado Pago.</span>
      </div>
      <button class="btn btn-block" type="submit">Guardar credenciales</button>

      <?php $ultimas = filas('SELECT tipo, accion, recurso_id, firma, entorno, creado_en
                                FROM pagos_webhook ORDER BY id DESC LIMIT 5'); ?>
      <p class="campo-label mt-2">Últimas notificaciones recibidas</p>
      <?php if (!$ultimas): ?>
        <p class="txt-sm txt-muted mb-0">Ninguna todavía. Usa «Simular notificación» en el panel de Mercado Pago para probar.</p>
      <?php else: ?>
        <table class="tabla tabla-mini">
          <tbody>
          <?php foreach ($ultimas as $n): ?>
            <tr>
              <td><?= h($n['tipo'] ?: '—') ?><br><span class="txt-sm txt-muted"><?= h($n['recurso_id'] ?: '') ?></span></td>
              <td>
                <?php if ($n['firma'] === 'valida'): ?><span class="chip chip-verde">firma válida</span>
                <?php elseif ($n['firma'] === 'invalida'): ?><span class="chip chip-rojo">firma inválida</span>
                <?php else: ?><span class="chip chip-gris">sin clave</span><?php endif; ?>
              </td>
              <td class="txt-sm txt-muted"><?= fecha_rel($n['creado_en']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <p class="campo-label mt-2">URL de retorno</p>
      <p class="txt-sm mb-2">
        <code class="mono copiar" data-copiar="<?= h(mp_url_retorno()) ?>"
              style="display:block;padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-alt);word-break:break-all"><?= h(mp_url_retorno()) ?></code>
        <span class="pista">A donde vuelve el comprador tras pagar. El portal la envía en cada preferencia; no hay que registrarla en el panel.</span>
      </p>

      <p class="txt-sm txt-muted mt-2 mb-0">
        Integración por <strong>Checkout Pro</strong>: el comprador paga en el sitio de Mercado Pago
        y el portal nunca recibe datos de tarjeta. Alcance <strong>PCI DSS SAQ A</strong>.
      </p>
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

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="microsoft">
      <div class="panel-h">
        <h3>Ingreso con Microsoft</h3>
        <?= sso_configurado() ? '<span class="chip chip-verde">Activo</span>'
                              : '<span class="chip chip-gris">Sin configurar</span>' ?>
      </div>

      <p class="campo-label">Dirección de retorno</p>
      <p class="txt-sm mb-2">
        <code class="mono copiar" data-copiar="<?= h(sso_url_retorno()) ?>"
              style="display:block;padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-alt);word-break:break-all"><?= h(sso_url_retorno()) ?></code>
        <span class="pista">Pégala en Azure → tu aplicación → <strong>Authentication</strong> → Redirect URIs,
          como plataforma <strong>Web</strong>. Debe coincidir carácter por carácter.</span>
      </p>

      <div class="campo-fila">
        <div class="campo">
          <label for="entra_cliente">Application (client) ID</label>
          <input type="text" id="entra_cliente" name="entra_cliente" value="<?= h(sso_cliente()) ?>"
                 placeholder="00000000-0000-0000-0000-000000000000">
        </div>
        <div class="campo">
          <label for="entra_tenant">Directory (tenant) ID</label>
          <input type="text" id="entra_tenant" name="entra_tenant" value="<?= h(sso_tenant()) ?>"
                 placeholder="00000000-0000-0000-0000-000000000000">
        </div>
      </div>
      <div class="campo">
        <label for="entra_secreto">Client secret</label>
        <input type="password" id="entra_secreto" name="entra_secreto" autocomplete="off"
               placeholder="<?= sso_secreto() !== '' ? 'Guardado (' . h(sso_pista(sso_secreto())) . '). Escribe uno nuevo para reemplazarlo.' : 'El valor del secreto, no su Id' ?>">
        <span class="pista">Azure → Certificates &amp; secrets → New client secret. Se copia el
          <strong>Value</strong>, no el <em>Secret ID</em>, y solo se ve una vez. Caduca: anota la fecha.</span>
      </div>
      <div class="campo">
        <label for="entra_dominios">Dominios de correo admitidos</label>
        <input type="text" id="entra_dominios" name="entra_dominios" value="<?= h(implode(', ', sso_dominios())) ?>"
               placeholder="colegioaleman.edu.co, estudiantes.colegioaleman.edu.co">
        <span class="pista">Separados por comas. Vacío admite cualquier cuenta del inquilino.</span>
      </div>
      <label class="check">
        <input type="checkbox" name="entra_alta" value="1" <?= sso_alta_automatica() ? 'checked' : '' ?>>
        <span>Crear la cuenta la primera vez que alguien entre</span>
      </label>
      <div class="campo">
        <label for="entra_rol">Rol de las cuentas creadas así</label>
        <select id="entra_rol" name="entra_rol">
          <?php foreach (ROLES as $k => $v): if ($k === 'admin') continue; ?>
            <option value="<?= $k ?>" <?= sso_rol_alta() === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Guardar</button>
      <p class="txt-sm txt-muted mt-2 mb-0">
        Con el alta automática apagada —como está de fábrica— solo entran las cuentas que ya
        existen en el portal; a las demás se les dice que pidan el alta. Es lo prudente en un
        colegio: que el ingreso sea cómodo no debería significar que cualquiera con correo del
        dominio se cree una cuenta sin que nadie lo mire.
      </p>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="asistente">
      <div class="panel-h">
        <h3>Asistente de IA</h3>
        <?= ia_configurada() ? '<span class="chip chip-verde">Configurado</span>'
                             : '<span class="chip chip-gris">Sin clave</span>' ?>
      </div>
      <div class="campo">
        <label for="ia_clave">Clave de la API de Google</label>
        <input type="password" id="ia_clave" name="ia_clave" autocomplete="off"
               placeholder="<?= ia_clave() !== '' ? 'Guardada (' . h(ia_pista(ia_clave())) . '). Escribe una nueva para reemplazarla.' : 'AIza…' ?>">
        <span class="pista">Se saca de <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.
          Si prefieres dejarla fuera del portal, defínela en <code>includes/config.local.php</code>: esa constante tiene prioridad.</span>
      </div>
      <div class="campo">
        <label for="ia_modelo">Modelo</label>
        <select id="ia_modelo" name="ia_modelo">
          <?php foreach (IA_MODELOS as $k => $v): ?>
            <option value="<?= h($k) ?>" <?= ia_modelo() === $k ? 'selected' : '' ?>><?= h($k) ?> — <?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="btn-fila">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn btn-ghost" name="accion" value="asistente_probar">Probar conexión</button>
      </div>
      <?php $consumo = ia_consumo_mes(); ?>
      <p class="txt-sm txt-muted mt-2 mb-0">
        Este mes: <strong><?= (int) ($consumo['llamadas'] ?? 0) ?></strong> llamadas ·
        <strong><?= number_format((float) ($consumo['tokens'] ?? 0), 0, ',', '.') ?></strong> tokens
        <?= (int) ($consumo['fallos'] ?? 0) ? ' · ' . (int) $consumo['fallos'] . ' con error' : '' ?>.
        El permiso se concede docente por docente en
        <a href="<?= url('portal/admin/usuarios.php') ?>">Usuarios</a>; tener la clave no basta.
      </p>
    </form>

    <form method="post" class="panel">
      <?= csrf_campo() ?>
      <input type="hidden" name="accion" value="almacenamiento">
      <div class="panel-h">
        <h3>Adjuntos de las entregas</h3>
        <?= s3_configurado() ? '<span class="chip chip-verde">En S3</span>'
                             : '<span class="chip chip-ambar">En el disco del servidor</span>' ?>
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="s3_bucket">Bucket</label>
          <input type="text" id="s3_bucket" name="s3_bucket" value="<?= h(s3_bucket()) ?>" placeholder="mi-bucket">
        </div>
        <div class="campo">
          <label for="s3_region">Región</label>
          <input type="text" id="s3_region" name="s3_region" value="<?= h(s3_region()) ?>" placeholder="us-east-1">
        </div>
      </div>
      <div class="campo">
        <label for="s3_prefijo">Carpeta dentro del bucket</label>
        <input type="text" id="s3_prefijo" name="s3_prefijo" value="<?= h(s3_prefijo()) ?>" placeholder="vcodepro">
      </div>
      <div class="campo-fila">
        <div class="campo">
          <label for="s3_llave">Access key</label>
          <input type="password" id="s3_llave" name="s3_llave" autocomplete="off"
                 placeholder="<?= s3_llave() !== '' ? 'Guardada (' . h(s3_pista(s3_llave())) . ')' : 'AKIA…' ?>">
        </div>
        <div class="campo">
          <label for="s3_secreto">Secret access key</label>
          <input type="password" id="s3_secreto" name="s3_secreto" autocomplete="off"
                 placeholder="<?= s3_secreto() !== '' ? 'Guardada (' . h(s3_pista(s3_secreto())) . ')' : 'Pega aquí la clave' ?>">
        </div>
      </div>
      <div class="btn-fila">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn btn-ghost" name="accion" value="almacenamiento_probar">Probar almacenamiento</button>
      </div>
      <p class="txt-sm txt-muted mt-2 mb-0">
        El bucket debe ser <strong>privado</strong>: el portal sirve cada archivo con un enlace
        firmado que caduca en minutos. Sin credenciales, los adjuntos se guardan en el disco del
        servidor. Máximo <?= h(adjunto_peso(ADJUNTO_MAX_BYTES)) ?> por archivo; el servidor admite
        hasta <?= h(ini_get('upload_max_filesize')) ?>.
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
