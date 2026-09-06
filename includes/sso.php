<?php
/**
 * Inicio de sesión con la cuenta institucional de Microsoft (Entra ID).
 *
 * OpenID Connect con flujo de código de autorización y PKCE, en PHP puro: sin
 * SDK ni Composer, como el resto de integraciones del proyecto.
 *
 * Lo que sostiene la seguridad de esto, y no se debe recortar:
 *
 * - **La firma del id_token se verifica** contra las claves públicas del
 *   inquilino (JWKS), además del emisor, la audiencia, la caducidad y el
 *   `nonce`. Un token sin verificar es una identidad regalada.
 * - **`state` y `nonce` viajan en la sesión**, no en la URL de vuelta: el
 *   primero corta el CSRF sobre el propio inicio de sesión y el segundo impide
 *   reutilizar un id_token capturado.
 * - **No se crea gente sola.** Por defecto solo entran cuentas que ya existen
 *   en el portal. El alta automática es un ajuste que la coordinación
 *   enciende a conciencia, con dominio y rol acotados.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** Lee un dato de configuración: constante → variable de entorno → ajustes. */
function sso_dato(string $constante, string $entorno, string $clave, string $porDefecto = ''): string {
    if (defined($constante) && constant($constante) !== '') return (string) constant($constante);
    $env = getenv($entorno);
    if ($env !== false && $env !== '') return $env;
    return (string) ajuste($clave, $porDefecto);
}

function sso_cliente(): string { return sso_dato('ENTRA_CLIENTE', 'VCP_ENTRA_CLIENTE', 'entra_cliente'); }
function sso_tenant(): string  { return sso_dato('ENTRA_TENANT',  'VCP_ENTRA_TENANT',  'entra_tenant'); }
function sso_secreto(): string { return sso_dato('ENTRA_SECRETO', 'VCP_ENTRA_SECRETO', 'entra_secreto'); }

/** Dominios de correo admitidos, separados por comas. Vacío = cualquiera del inquilino. */
function sso_dominios(): array {
    $d = sso_dato('ENTRA_DOMINIOS', 'VCP_ENTRA_DOMINIOS', 'entra_dominios');
    return array_values(array_filter(array_map(
        fn($x) => mb_strtolower(trim($x, " \t.@")), explode(',', $d)
    )));
}

/** ¿Se dan de alta solas las cuentas nuevas del inquilino? Por defecto, no. */
function sso_alta_automatica(): bool { return ajuste('entra_alta', '0') === '1'; }
function sso_rol_alta(): string {
    $r = (string) ajuste('entra_rol', 'estudiante');
    return array_key_exists($r, ROLES) && $r !== 'admin' ? $r : 'estudiante';
}

function sso_configurado(): bool {
    return sso_cliente() !== '' && sso_tenant() !== '' && sso_secreto() !== '';
}

/** Últimos caracteres de una credencial, para mostrarla sin exponerla. */
function sso_pista(string $valor): string {
    return $valor === '' ? '' : '…' . substr($valor, -4);
}

function sso_base(): string {
    return 'https://login.microsoftonline.com/' . rawurlencode(sso_tenant());
}

/**
 * Dirección de retorno que hay que registrar en Azure como *Redirect URI*.
 * Tiene que coincidir carácter por carácter con lo que se registre allí.
 */
function sso_url_retorno(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'www.vcodepro.de';
    return esquema_publico() . '://' . $host . url('portal/sso.php');
}

// --------------------------------------------------------------- utilidades --

function sso_b64u_decode(string $s): string {
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}
function sso_b64u_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Petición HTTP sencilla; devuelve [codigo, arreglo]. */
function sso_http(string $url, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        // La validación TLS es parte del modelo de seguridad: el token llega
        // por este canal y confiamos en que el otro extremo es Microsoft.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode((string) $cuerpo, true);
    return [$codigo, is_array($d) ? $d : []];
}

/** Claves públicas del inquilino, con caché en disco de una hora. */
function sso_jwks(bool $forzar = false): array {
    $cache = RUTA_SUBIDAS . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR
           . 'jwks-' . substr(sha1(sso_tenant()), 0, 12) . '.json';
    if (!$forzar && is_readable($cache) && filemtime($cache) > time() - 3600) {
        $d = json_decode((string) file_get_contents($cache), true);
        if (is_array($d) && $d) return $d;
    }
    [$codigo, $d] = sso_http(sso_base() . '/discovery/v2.0/keys');
    if ($codigo !== 200 || empty($d['keys'])) return [];
    @mkdir(dirname($cache), 0775, true);
    @file_put_contents($cache, json_encode($d['keys']));
    return $d['keys'];
}

/** Clave pública en formato PEM a partir de una entrada del JWKS. */
function sso_pem(array $jwk): ?string {
    // Microsoft publica el certificado; es el camino corto y sin sorpresas.
    if (!empty($jwk['x5c'][0])) {
        return "-----BEGIN CERTIFICATE-----\n"
             . chunk_split((string) $jwk['x5c'][0], 64, "\n")
             . "-----END CERTIFICATE-----\n";
    }
    // Reserva: reconstruir la clave RSA desde el módulo y el exponente.
    if (empty($jwk['n']) || empty($jwk['e'])) return null;
    $entero = function (string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7F) $bytes = "\x00" . $bytes;
        return "\x02" . sso_der_longitud(strlen($bytes)) . $bytes;
    };
    $rsa = $entero(sso_b64u_decode($jwk['n'])) . $entero(sso_b64u_decode($jwk['e']));
    $sec = "\x30" . sso_der_longitud(strlen($rsa)) . $rsa;
    $bit = "\x03" . sso_der_longitud(strlen($sec) + 1) . "\x00" . $sec;
    $oid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $spki = "\x30" . sso_der_longitud(strlen($oid . $bit)) . $oid . $bit;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Longitud en DER (forma corta o larga). */
function sso_der_longitud(int $n): string {
    if ($n < 0x80) return chr($n);
    $b = ltrim(pack('N', $n), "\x00");
    return chr(0x80 | strlen($b)) . $b;
}

/**
 * Verifica el id_token y devuelve [true, $claims] o [false, 'motivo'].
 * Comprueba firma, emisor, audiencia, ventana temporal y nonce.
 */
function sso_validar_id_token(string $jwt, string $nonceEsperado): array {
    $partes = explode('.', $jwt);
    if (count($partes) !== 3) return [false, 'El token de Microsoft no tiene el formato esperado.'];

    [$cab64, $car64, $firma64] = $partes;
    $cab = json_decode(sso_b64u_decode($cab64), true);
    $claims = json_decode(sso_b64u_decode($car64), true);
    if (!is_array($cab) || !is_array($claims)) return [false, 'No se pudo leer el token de Microsoft.'];
    if (($cab['alg'] ?? '') !== 'RS256') return [false, 'El token viene firmado con un algoritmo que no aceptamos.'];

    // --- firma ---------------------------------------------------------------
    $kid = (string) ($cab['kid'] ?? '');
    $verificada = false;
    foreach ([false, true] as $forzar) {           // si el kid no está, se refresca el JWKS
        foreach (sso_jwks($forzar) as $jwk) {
            if (($jwk['kid'] ?? '') !== $kid) continue;
            $pem = sso_pem($jwk);
            if ($pem === null) continue;
            $pub = openssl_pkey_get_public($pem);
            if (!$pub) continue;
            $ok = openssl_verify("$cab64.$car64", sso_b64u_decode($firma64), $pub, OPENSSL_ALGO_SHA256);
            if ($ok === 1) { $verificada = true; }
            break 2;
        }
    }
    if (!$verificada) return [false, 'La firma del token de Microsoft no es válida.'];

    // --- contenido -----------------------------------------------------------
    $emisorEsperado = 'https://login.microsoftonline.com/' . sso_tenant() . '/v2.0';
    if (($claims['iss'] ?? '') !== $emisorEsperado) return [false, 'El token no lo emitió el inquilino configurado.'];
    if (($claims['aud'] ?? '') !== sso_cliente())   return [false, 'El token no está dirigido a esta aplicación.'];

    $ahora = time();
    $margen = 120;                                  // desfase de reloj tolerado
    if (($claims['exp'] ?? 0) < $ahora - $margen)   return [false, 'El token de Microsoft ya caducó. Inténtalo de nuevo.'];
    if (($claims['nbf'] ?? 0) > $ahora + $margen)   return [false, 'El token de Microsoft aún no es válido.'];

    if ($nonceEsperado === '' || !hash_equals($nonceEsperado, (string) ($claims['nonce'] ?? ''))) {
        return [false, 'La respuesta de Microsoft no corresponde a esta solicitud.'];
    }
    if (($claims['tid'] ?? '') !== sso_tenant()) return [false, 'La cuenta no pertenece al inquilino del colegio.'];

    return [true, $claims];
}

// ------------------------------------------------------------------- flujo --

/** Prepara la sesión y devuelve la URL a la que hay que enviar al navegador. */
function sso_url_autorizacion(?string $destino = null): string {
    iniciar_sesion();
    $estado = bin2hex(random_bytes(16));
    $nonce  = bin2hex(random_bytes(16));
    // PKCE: aunque el cliente es confidencial, encarece robar el código.
    $verificador = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

    $_SESSION['sso'] = [
        'estado'      => $estado,
        'nonce'       => $nonce,
        'verificador' => $verificador,
        'destino'     => $destino,
        'creado'      => time(),
    ];

    return sso_base() . '/oauth2/v2.0/authorize?' . http_build_query([
        'client_id'             => sso_cliente(),
        'response_type'         => 'code',
        'redirect_uri'          => sso_url_retorno(),
        'response_mode'         => 'query',
        'scope'                 => 'openid profile email',
        'state'                 => $estado,
        'nonce'                 => $nonce,
        'code_challenge'        => sso_b64u_encode(hash('sha256', $verificador, true)),
        'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Canjea el código por los tokens. Devuelve [true, $tokens] o [false, 'motivo']. */
function sso_canjear(string $codigo, string $verificador): array {
    [$http, $d] = sso_http(sso_base() . '/oauth2/v2.0/token', [
        'client_id'     => sso_cliente(),
        'client_secret' => sso_secreto(),
        'grant_type'    => 'authorization_code',
        'code'          => $codigo,
        'redirect_uri'  => sso_url_retorno(),
        'code_verifier' => $verificador,
        'scope'         => 'openid profile email',
    ]);
    if ($http !== 200 || empty($d['id_token'])) {
        $desc = (string) ($d['error_description'] ?? $d['error'] ?? '');
        if (str_contains($desc, 'AADSTS7000215')) return [false, 'El secreto de la aplicación no es correcto.'];
        if (str_contains($desc, 'AADSTS50011'))   return [false, 'La dirección de retorno no coincide con la registrada en Azure.'];
        if (str_contains($desc, 'AADSTS700016'))  return [false, 'El identificador de aplicación no existe en este inquilino.'];
        return [false, 'Microsoft rechazó la solicitud' . ($desc !== '' ? ': ' . corte($desc, 200) : '.')];
    }
    return [true, $d];
}

/** Correo de la cuenta, mirando los campos que Entra puede usar. */
function sso_correo(array $c): string {
    foreach (['email', 'preferred_username', 'upn', 'unique_name'] as $k) {
        $v = mb_strtolower(trim((string) ($c[$k] ?? '')));
        if ($v !== '' && str_contains($v, '@')) return $v;
    }
    return '';
}

/**
 * Encuentra o crea la cuenta local a partir de los datos de Microsoft.
 * Devuelve [true, $usuario] o [false, 'motivo'].
 */
function sso_usuario_de(array $c): array {
    $oid    = (string) ($c['oid'] ?? $c['sub'] ?? '');
    $correo = sso_correo($c);
    if ($oid === '' || $correo === '') return [false, 'Microsoft no entregó el correo de la cuenta.'];

    $dominios = sso_dominios();
    if ($dominios) {
        $suyo = mb_strtolower(substr(strrchr($correo, '@') ?: '', 1));
        if (!in_array($suyo, $dominios, true)) {
            return [false, 'La cuenta ' . $correo . ' no pertenece a un dominio autorizado.'];
        }
    }

    // Primero por identificador de Microsoft: sobrevive a un cambio de correo.
    $u = fila('SELECT * FROM usuarios WHERE entra_oid = ?', [$oid])
      ?: fila('SELECT * FROM usuarios WHERE email = ?', [$correo]);

    if (!$u) {
        if (!sso_alta_automatica()) {
            return [false, 'No hay ninguna cuenta en el portal para ' . $correo
                         . '. Pídele a la coordinación que te dé de alta.'];
        }
        $nombre = trim((string) ($c['given_name'] ?? ''));
        $apellidos = trim((string) ($c['family_name'] ?? ''));
        if ($nombre === '') {
            $completo = trim((string) ($c['name'] ?? $correo));
            $partes = preg_split('/\s+/', $completo) ?: [$completo];
            $nombre = array_shift($partes) ?: $correo;
            $apellidos = implode(' ', $partes);
        }
        $id = insertar('usuarios', [
            'colegio_id'    => valor('SELECT id FROM colegios ORDER BY id LIMIT 1') ?: null,
            'nombre'        => mb_substr($nombre, 0, 120),
            'apellidos'     => mb_substr($apellidos, 0, 120),
            'email'         => $correo,
            // Sin contraseña utilizable: esta cuenta entra por Microsoft.
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'rol'           => sso_rol_alta(),
            'estado'        => 'activo',
            'origen'        => 'microsoft',
            'entra_oid'     => $oid,
        ]);
        auditar('sso_alta', 'usuarios', $id, $correo);
        return [true, fila('SELECT * FROM usuarios WHERE id = ?', [$id])];
    }

    if ($u['estado'] === 'suspendido') return [false, 'Tu cuenta está suspendida. Escribe a la coordinación.'];
    if ($u['estado'] === 'pendiente')  return [false, 'Tu cuenta todavía está pendiente de aprobación.'];

    // Enlace en el primer ingreso, para que el correo deje de ser la única llave.
    if ((string) $u['entra_oid'] !== $oid) {
        actualizar('usuarios', ['entra_oid' => $oid], 'id = :id', ['id' => $u['id']]);
        auditar('sso_enlace', 'usuarios', (int) $u['id'], $correo);
        $u['entra_oid'] = $oid;
    }
    return [true, $u];
}
