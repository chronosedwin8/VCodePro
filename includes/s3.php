<?php
/**
 * Cliente mínimo de Amazon S3 para los adjuntos de las entregas.
 *
 * El proyecto no usa Composer, así que aquí no hay SDK: la firma SigV4 se
 * calcula a mano con hash_hmac, que es lo único que hace falta para subir,
 * descargar y borrar objetos.
 *
 * Dos decisiones que conviene no deshacer:
 *
 * 1. **El bucket es privado.** Lo que sube un estudiante es trabajo escolar de
 *    un menor de edad: no puede quedar en una URL pública que cualquiera
 *    adivine o que acabe indexada. Las descargas se sirven con enlaces
 *    firmados de vida corta (s3_url_temporal), y el portal solo los genera
 *    para quien tiene permiso de ver esa entrega.
 * 2. **El archivo se sube desde el servidor**, no desde el navegador con una
 *    URL prefirmada. Así las credenciales de AWS nunca salen del servidor y el
 *    archivo se valida —extensión, tamaño— antes de existir en S3.
 *
 * Las credenciales no se escriben aquí: van en includes/config.local.php, que
 * está fuera del control de versiones.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Lee un dato de configuración: constante → variable de entorno → ajustes. */
function s3_dato(string $constante, string $entorno, string $clave, string $porDefecto = ''): string {
    if (defined($constante) && constant($constante) !== '') return (string) constant($constante);
    $env = getenv($entorno);
    if ($env !== false && $env !== '') return $env;
    return (string) ajuste($clave, $porDefecto);
}

function s3_bucket(): string  { return s3_dato('S3_BUCKET',  'VCP_S3_BUCKET',  's3_bucket'); }
function s3_region(): string  { return s3_dato('S3_REGION',  'VCP_S3_REGION',  's3_region', 'us-east-1'); }
function s3_llave(): string   { return s3_dato('S3_LLAVE',   'VCP_S3_LLAVE',   's3_llave'); }
function s3_secreto(): string { return s3_dato('S3_SECRETO', 'VCP_S3_SECRETO', 's3_secreto'); }

/** Prefijo opcional dentro del bucket, por si se comparte con otro proyecto. */
function s3_prefijo(): string {
    return trim(s3_dato('S3_PREFIJO', 'VCP_S3_PREFIJO', 's3_prefijo'), '/');
}

function s3_configurado(): bool {
    return s3_bucket() !== '' && s3_llave() !== '' && s3_secreto() !== '';
}

/** Últimos caracteres de una credencial, para mostrarla sin exponerla. */
function s3_pista(string $valor): string {
    return $valor === '' ? '' : '…' . substr($valor, -4);
}

function s3_anfitrion(): string {
    return s3_bucket() . '.s3.' . s3_region() . '.amazonaws.com';
}

/** Codifica la ruta del objeto dejando las barras como separadores. */
function s3_ruta_codificada(string $clave): string {
    $partes = array_map(fn($p) => rawurlencode($p), explode('/', ltrim($clave, '/')));
    return '/' . implode('/', $partes);
}

/** Cadena de claves derivadas de SigV4: fecha → región → servicio → firma. */
function s3_clave_firma(string $fecha): string {
    $k = hash_hmac('sha256', $fecha, 'AWS4' . s3_secreto(), true);
    $k = hash_hmac('sha256', s3_region(), $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    return hash_hmac('sha256', 'aws4_request', $k, true);
}

/**
 * Firma una petición con SigV4 y devuelve las cabeceras listas para cURL.
 *
 * @param string $hashCuerpo sha256 del contenido en hexadecimal, o
 *                           'UNSIGNED-PAYLOAD' cuando no se conoce.
 */
function s3_cabeceras_firmadas(string $metodo, string $clave, string $hashCuerpo, array $extra = []): array {
    $ahora   = gmdate('Ymd\THis\Z');
    $fecha   = substr($ahora, 0, 8);
    $ambito  = "$fecha/" . s3_region() . '/s3/aws4_request';

    $cab = array_merge([
        'host'                 => s3_anfitrion(),
        'x-amz-content-sha256' => $hashCuerpo,
        'x-amz-date'           => $ahora,
    ], array_change_key_case($extra, CASE_LOWER));
    ksort($cab);

    $canonicas = '';
    foreach ($cab as $k => $v) $canonicas .= $k . ':' . trim((string) $v) . "\n";
    $firmadas = implode(';', array_keys($cab));

    $peticion = implode("\n", [
        $metodo,
        s3_ruta_codificada($clave),
        '',                       // sin parámetros de consulta
        $canonicas,
        $firmadas,
        $hashCuerpo,
    ]);

    $porFirmar = implode("\n", [
        'AWS4-HMAC-SHA256',
        $ahora,
        $ambito,
        hash('sha256', $peticion),
    ]);
    $firma = hash_hmac('sha256', $porFirmar, s3_clave_firma($fecha));

    $salida = [];
    foreach ($cab as $k => $v) if ($k !== 'host') $salida[] = $k . ': ' . $v;
    $salida[] = 'Authorization: AWS4-HMAC-SHA256 '
        . 'Credential=' . s3_llave() . "/$ambito, "
        . "SignedHeaders=$firmadas, Signature=$firma";
    return $salida;
}

/**
 * Enlace temporal de descarga (SigV4 por parámetros de consulta).
 * Vive pocos minutos: el tiempo de que el navegador siga la redirección.
 */
function s3_url_temporal(string $clave, int $segundos = 300, string $nombreDescarga = ''): string {
    if (!s3_configurado()) return '';
    $ahora  = gmdate('Ymd\THis\Z');
    $fecha  = substr($ahora, 0, 8);
    $ambito = "$fecha/" . s3_region() . '/s3/aws4_request';

    $params = [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => s3_llave() . '/' . $ambito,
        'X-Amz-Date'          => $ahora,
        'X-Amz-Expires'       => (string) max(60, min(604800, $segundos)),
        'X-Amz-SignedHeaders' => 'host',
    ];
    if ($nombreDescarga !== '') {
        // Con esto el navegador guarda el archivo con su nombre original y no
        // con el identificador aleatorio que usamos como clave en el bucket.
        $params['response-content-disposition'] =
            'attachment; filename="' . str_replace('"', '', $nombreDescarga) . '"';
    }
    ksort($params);
    $consulta = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $peticion = implode("\n", [
        'GET',
        s3_ruta_codificada($clave),
        $consulta,
        'host:' . s3_anfitrion() . "\n",
        'host',
        'UNSIGNED-PAYLOAD',
    ]);
    $porFirmar = implode("\n", ['AWS4-HMAC-SHA256', $ahora, $ambito, hash('sha256', $peticion)]);
    $firma = hash_hmac('sha256', $porFirmar, s3_clave_firma($fecha));

    return 'https://' . s3_anfitrion() . s3_ruta_codificada($clave)
        . '?' . $consulta . '&X-Amz-Signature=' . $firma;
}

/**
 * Sube un archivo del disco a S3.
 * Devuelve [true, ''] o [false, motivo].
 */
function s3_subir(string $rutaLocal, string $clave, string $tipoMime = 'application/octet-stream'): array {
    if (!s3_configurado())    return [false, 'S3 no está configurado.'];
    if (!is_readable($rutaLocal)) return [false, 'No se pudo leer el archivo subido.'];

    $hash = hash_file('sha256', $rutaLocal);
    if ($hash === false) return [false, 'No se pudo calcular la huella del archivo.'];

    $cabeceras = s3_cabeceras_firmadas('PUT', $clave, $hash, [
        'content-type' => $tipoMime,
        // Cifrado en reposo: lo aplica S3 por su cuenta, sin gestionar claves.
        'x-amz-server-side-encryption' => 'AES256',
    ]);

    $fh = fopen($rutaLocal, 'rb');
    if (!$fh) return [false, 'No se pudo abrir el archivo subido.'];

    $ch = curl_init('https://' . s3_anfitrion() . s3_ruta_codificada($clave));
    curl_setopt_array($ch, [
        CURLOPT_PUT            => true,
        CURLOPT_INFILE         => $fh,
        CURLOPT_INFILESIZE     => filesize($rutaLocal),
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errCurl = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if ($codigo >= 200 && $codigo < 300) return [true, ''];
    return [false, s3_motivo($codigo, (string) $cuerpo, $errCurl)];
}

/** Borra un objeto. S3 responde 204 aunque no existiera. */
function s3_borrar(string $clave): bool {
    if (!s3_configurado()) return false;
    $vacio = hash('sha256', '');
    $ch = curl_init('https://' . s3_anfitrion() . s3_ruta_codificada($clave));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => s3_cabeceras_firmadas('DELETE', $clave, $vacio),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $codigo >= 200 && $codigo < 300;
}

/** Comprueba que el objeto existe (HEAD). */
function s3_existe(string $clave): bool {
    if (!s3_configurado()) return false;
    $ch = curl_init('https://' . s3_anfitrion() . s3_ruta_codificada($clave));
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_HTTPHEADER     => s3_cabeceras_firmadas('HEAD', $clave, 'UNSIGNED-PAYLOAD'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $codigo === 200;
}

/** Traduce el error de S3 a algo que un docente pueda entender. */
function s3_motivo(int $codigo, string $cuerpo, string $errCurl = ''): string {
    if ($errCurl !== '') return 'No se pudo conectar con el almacenamiento: ' . $errCurl;
    $xml = [];
    if (preg_match('#<Code>([^<]+)</Code>#', $cuerpo, $m)) $xml['codigo'] = $m[1];
    return match ($xml['codigo'] ?? '') {
        'AccessDenied'     => 'El almacenamiento rechazó la subida: la cuenta no tiene permiso sobre ese bucket.',
        'InvalidAccessKeyId', 'SignatureDoesNotMatch'
                           => 'Las credenciales del almacenamiento no son válidas.',
        'NoSuchBucket'     => 'El bucket configurado no existe.',
        'EntityTooLarge'   => 'El archivo es demasiado grande para el almacenamiento.',
        default            => 'El almacenamiento respondió ' . $codigo
                              . (isset($xml['codigo']) ? ' (' . $xml['codigo'] . ')' : '') . '.',
    };
}

/** Diagnóstico para el panel de ajustes: sube, lee y borra un objeto de prueba. */
function s3_probar(): array {
    if (!s3_configurado()) return [false, 'Faltan datos de configuración.'];
    $tmp = tempnam(sys_get_temp_dir(), 'vcp');
    if ($tmp === false) return [false, 'No se pudo crear el archivo de prueba.'];
    file_put_contents($tmp, 'VCodePro ' . gmdate('c'));
    $clave = trim(s3_prefijo() . '/diagnostico/' . bin2hex(random_bytes(8)) . '.txt', '/');

    [$ok, $error] = s3_subir($tmp, $clave, 'text/plain');
    @unlink($tmp);
    if (!$ok) return [false, $error];
    if (!s3_existe($clave)) return [false, 'La subida no dio error pero el objeto no aparece en el bucket.'];
    s3_borrar($clave);
    return [true, 'Subida, lectura y borrado correctos en ' . s3_bucket() . ' (' . s3_region() . ').'];
}
