<?php
/**
 * Ida y vuelta del inicio de sesión con Microsoft.
 *
 * Este mismo archivo es el que arranca el flujo y el que recibe la respuesta,
 * así en Azure solo hay que registrar **una** dirección de retorno.
 *
 *   /portal/sso.php            → manda al usuario a Microsoft
 *   /portal/sso.php?code=…     → Microsoft devuelve aquí y aquí se abre sesión
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/sso.php';

iniciar_sesion();

function sso_fallo(string $motivo): never {
    unset($_SESSION['sso']);
    auditar('sso_fallido', null, null, corte($motivo, 200));
    flash_err($motivo);
    redirigir('portal/login.php');
}

if (!sso_configurado()) {
    flash_err('El ingreso con Microsoft no está configurado todavía.');
    redirigir('portal/login.php');
}
if (usuario()) redirigir(panel_de(rol()));

// ------------------------------------------------------------------- ida ----
if (get('code') === '' && get('error') === '') {
    $destino = get('destino') ?: ($_SESSION['destino'] ?? null);
    // redirigir() reenvía tal cual cuando la ruta ya es absoluta.
    redirigir(sso_url_autorizacion($destino));
}

// ----------------------------------------------------------------- vuelta ---
if (get('error') !== '') {
    // El usuario canceló, o el administrador no ha dado el consentimiento.
    $desc = get('error_description');
    sso_fallo($desc !== ''
        ? 'Microsoft no autorizó el ingreso: ' . corte($desc, 200)
        : 'Microsoft no autorizó el ingreso.');
}

$guardado = $_SESSION['sso'] ?? null;
if (!is_array($guardado)) {
    sso_fallo('La solicitud de ingreso caducó. Vuelve a intentarlo.');
}
// Diez minutos son de sobra para escribir una contraseña y un segundo factor.
if (time() - (int) ($guardado['creado'] ?? 0) > 600) {
    sso_fallo('La solicitud de ingreso caducó. Vuelve a intentarlo.');
}
if (!hash_equals((string) $guardado['estado'], get('state'))) {
    sso_fallo('La respuesta de Microsoft no corresponde a esta solicitud.');
}

[$ok, $tokens] = sso_canjear(get('code'), (string) $guardado['verificador']);
if (!$ok) sso_fallo($tokens);

[$ok, $claims] = sso_validar_id_token((string) $tokens['id_token'], (string) $guardado['nonce']);
if (!$ok) sso_fallo($claims);

[$ok, $u] = sso_usuario_de($claims);
if (!$ok) sso_fallo($u);

$destino = $guardado['destino'] ?? null;
unset($_SESSION['sso'], $_SESSION['destino']);

abrir_sesion_usuario($u);
auditar('login_microsoft', 'usuarios', (int) $u['id'], sso_correo($claims));
flash_ok('Bienvenido, ' . $u['nombre'] . '.');
redirigir($destino ?: panel_de($u['rol']));
