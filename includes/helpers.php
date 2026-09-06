<?php
/**
 * Funciones de apoyo: escape, URLs, mensajes flash, CSRF, formatos.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ------------------------------------------------------------------ sesión --
function iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESION_NOMBRE);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
    if (!isset($_SESSION['creada'])) {
        $_SESSION['creada'] = time();
    } elseif (time() - $_SESSION['creada'] > SESION_VIDA) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['creada'] = time();
    }
}

// ------------------------------------------------------------------ escape --
/**
 * Escapa para HTML. Acepta números además de texto porque las columnas
 * numéricas de la base llegan como int o float a las plantillas.
 */
function h(string|int|float|null $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Texto plano con saltos de línea preservados. */
function nl(?string $s): string {
    return nl2br(h($s));
}

/** Recorta un texto a n caracteres. */
function corte(?string $s, int $n = 120): string {
    $s = trim(strip_tags((string) $s));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}

// -------------------------------------------------------------------- URLs --
function url(string $ruta = ''): string {
    return BASE_URL . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta, int $codigo = 302): never {
    $destino = str_starts_with($ruta, 'http') ? $ruta : url($ruta);
    header('Location: ' . $destino, true, $codigo);
    exit;
}

/**
 * Esquema real con el que el visitante llegó al sitio.
 *
 * Detrás de Nginx o de CloudPanel, PHP recibe la petición por HTTP y
 * $_SERVER['HTTPS'] viene vacío aunque el navegador esté en HTTPS. Quien sabe
 * la verdad es la cabecera X-Forwarded-Proto que pone el proxy. Importa para
 * cualquier dirección que se le entregue a un tercero —la de retorno de
 * Microsoft, la del webhook de la pasarela—: si la construimos como http, no
 * coincide con la registrada y la integración falla.
 */
function esquema_publico(): string {
    $reenviado = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($reenviado !== '') {
        // Puede llegar encadenado: "https, http".
        $primero = trim(explode(',', $reenviado)[0]);
        if ($primero !== '') return strtolower($primero) === 'https' ? 'https' : 'http';
    }
    if (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') return 'https';
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) return 'https';
    $https = $_SERVER['HTTPS'] ?? '';
    return $https !== '' && strtolower((string) $https) !== 'off' ? 'https' : 'http';
}

function url_actual(): string {
    return $_SERVER['REQUEST_URI'] ?? url('portal/');
}

/** Devuelve ' aria-current="page"' cuando el archivo actual coincide. */
function activo(string ...$archivos): string {
    $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return in_array($actual, $archivos, true) ? ' class="on" aria-current="page"' : '';
}

// ------------------------------------------------------------------- flash --
function flash(string $tipo, string $mensaje): void {
    iniciar_sesion();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_ok(string $m): void  { flash('ok', $m); }
function flash_err(string $m): void { flash('err', $m); }

function sacar_flash(): array {
    iniciar_sesion();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function pintar_flash(): string {
    $out = '';
    foreach (sacar_flash() as $f) {
        $out .= '<div class="aviso aviso-' . h($f['tipo']) . '" role="status">' . h($f['mensaje']) . '</div>';
    }
    return $out;
}

// -------------------------------------------------------------------- CSRF --
function csrf_token(): string {
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valido(): bool {
    iniciar_sesion();
    $t = $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Aborta si la petición POST no trae un token válido. */
function exigir_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
        http_response_code(419);
        exit('Token de seguridad inválido o vencido. Vuelve atrás y recarga la página.');
    }
}

// ------------------------------------------------------------------ entrada --
/**
 * Descarta los bytes que no formen UTF-8 válido. Sin esto, un formulario
 * enviado con otra codificación rompe la inserción en una tabla utf8mb4.
 */
function utf8_limpio(string $s): string {
    if (mb_check_encoding($s, 'UTF-8')) return $s;
    return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
}

function post(string $campo, $porDefecto = ''): string {
    $v = $_POST[$campo] ?? $porDefecto;
    return is_string($v) ? trim(utf8_limpio($v)) : (string) $porDefecto;
}

function get(string $campo, $porDefecto = ''): string {
    $v = $_GET[$campo] ?? $porDefecto;
    return is_string($v) ? trim(utf8_limpio($v)) : (string) $porDefecto;
}

function post_int(string $campo, int $porDefecto = 0): int {
    return isset($_POST[$campo]) ? (int) $_POST[$campo] : $porDefecto;
}

function get_int(string $campo, int $porDefecto = 0): int {
    return isset($_GET[$campo]) ? (int) $_GET[$campo] : $porDefecto;
}

function es_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_salida($datos, int $codigo = 200): never {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------------------- formatos --
function fecha(?string $f, bool $conHora = false): string {
    if (!$f) return '—';
    $ts = strtotime($f);
    if (!$ts) return '—';
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $s = date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $conHora ? $s . ' · ' . date('H:i', $ts) : $s;
}

function fecha_rel(?string $f): string {
    if (!$f) return '—';
    $ts = strtotime($f);
    if (!$ts) return '—';
    $d = time() - $ts;
    if ($d < 60)    return 'hace un momento';
    if ($d < 3600)  return 'hace ' . intdiv($d, 60) . ' min';
    if ($d < 86400) return 'hace ' . intdiv($d, 3600) . ' h';
    if ($d < 2592000) return 'hace ' . intdiv($d, 86400) . ' d';
    return fecha($f);
}

/** Días que faltan para una fecha (negativo si ya pasó). */
function dias_para(?string $f): int {
    if (!$f) return 0;
    $hoy = new DateTime('today');
    $obj = new DateTime($f);
    return (int) $hoy->diff($obj)->format('%r%a');
}

function moneda(float $v, string $m = 'COP'): string {
    return number_format($v, 0, ',', '.') . ' ' . $m;
}

function porcentaje(float $parte, float $total): int {
    if ($total <= 0) return 0;
    return (int) round(($parte / $total) * 100);
}

function iniciales(string $nombre, string $apellidos = ''): string {
    $a = mb_substr(trim($nombre), 0, 1);
    $b = $apellidos !== '' ? mb_substr(trim($apellidos), 0, 1) : '';
    return mb_strtoupper($a . $b);
}

/**
 * Texto a identificador de URL.
 *
 * No se usa iconv con //TRANSLIT: en Windows convierte «á» en «'a» y deja
 * slugs como «bogot-a». La tabla explícita da el mismo resultado en cualquier
 * sistema operativo.
 */
function slug(string $s): string {
    $mapa = [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
        'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
        'ñ'=>'n','ç'=>'c','ß'=>'ss','ý'=>'y','ÿ'=>'y',
    ];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $mapa);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/** Lista separada por comas → arreglo limpio. */
function lista(?string $s): array {
    if (!$s) return [];
    return array_values(array_filter(array_map('trim', explode('|', $s)), fn($x) => $x !== ''));
}

// ------------------------------------------------------------------- estado --
function etiqueta_estado(string $estado): string {
    $mapa = [
        'pendiente'   => ['Pendiente', 'gris'],
        'en_progreso' => ['En progreso', 'azul'],
        'entregada'   => ['Entregada', 'ambar'],
        'revisada'    => ['Calificada', 'verde'],
        'rehacer'     => ['Debe rehacer', 'rojo'],
        'activo'      => ['Activo', 'verde'],
        'activa'      => ['Activa', 'verde'],
        'archivado'   => ['Archivado', 'gris'],
        'suspendido'  => ['Suspendido', 'rojo'],
        'suspendida'  => ['Suspendida', 'rojo'],
        'vencida'     => ['Vencida', 'rojo'],
        'abierta'     => ['Abierta', 'verde'],
        'cerrada'     => ['Cerrada', 'gris'],
        'borrador'    => ['Borrador', 'gris'],
        'pagada'      => ['Pagada', 'verde'],
        'anulada'     => ['Anulada', 'gris'],
        'abierto'     => ['Abierto', 'azul'],
        'resuelto'    => ['Resuelto', 'verde'],
        'cerrado'     => ['Cerrado', 'gris'],
        'nuevo'       => ['Nuevo', 'azul'],
        'atendido'    => ['Atendido', 'verde'],
        'retirado'    => ['Retirado', 'gris'],
        'prueba'      => ['En prueba', 'ambar'],
    ];
    [$texto, $color] = $mapa[$estado] ?? [ucfirst(str_replace('_', ' ', $estado)), 'gris'];
    return '<span class="chip chip-' . $color . '">' . h($texto) . '</span>';
}

/** Nota IB 1–7 a partir del total de criterios (sobre 32). */
function nota_ib(float $total, float $maximo = 32.0): array {
    $p = $maximo > 0 ? ($total / $maximo) * 100 : 0;
    return match (true) {
        $p >= 90 => ['7', 'Excelente'],
        $p >= 78 => ['6', 'Muy bueno'],
        $p >= 65 => ['5', 'Bueno'],
        $p >= 50 => ['4', 'Satisfactorio'],
        $p >= 37 => ['3', 'Mediocre'],
        $p >= 22 => ['2', 'Deficiente'],
        default  => ['1', 'Muy deficiente'],
    };
}

// -------------------------------------------------------------- auditoría ---
function auditar(string $accion, ?string $entidad = null, ?int $entidadId = null, ?string $detalle = null): void {
    try {
        insertar('auditoria', [
            'usuario_id' => $_SESSION['usuario_id'] ?? null,
            'accion'     => $accion,
            'entidad'    => $entidad,
            'entidad_id' => $entidadId,
            'detalle'    => $detalle ? mb_substr($detalle, 0, 400) : null,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* la auditoría nunca debe romper el flujo */ }
}

function notificar(int $usuarioId, string $titulo, string $mensaje, ?string $urlDestino = null, string $tipo = 'info'): void {
    try {
        insertar('notificaciones', [
            'usuario_id' => $usuarioId,
            'tipo'       => $tipo,
            'titulo'     => mb_substr($titulo, 0, 200),
            'mensaje'    => mb_substr($mensaje, 0, 400),
            'url'        => $urlDestino,
        ]);
    } catch (Throwable $e) { /* silencioso */ }
}

// ---------------------------------------------------------------- archivos --
/**
 * Guarda un archivo subido dentro de assets/uploads/<carpeta>.
 * Devuelve [ruta_relativa, nombre_original] o null.
 */
function guardar_subida(array $archivo, string $carpeta): ?array {
    if (!isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK) return null;
    if ($archivo['size'] > SUBIDA_MAX_BYTES) {
        flash_err('El archivo supera el tamaño máximo permitido (12 MB).');
        return null;
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, SUBIDA_EXTENSIONES, true)) {
        flash_err('Extensión de archivo no permitida: .' . h($ext));
        return null;
    }
    $destinoDir = RUTA_SUBIDAS . DIRECTORY_SEPARATOR . $carpeta;
    if (!is_dir($destinoDir)) @mkdir($destinoDir, 0775, true);

    $nombre = bin2hex(random_bytes(8)) . '-' . date('Ymd') . '.' . $ext;
    $ruta   = $destinoDir . DIRECTORY_SEPARATOR . $nombre;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta)) return null;

    return [$carpeta . '/' . $nombre, mb_substr($archivo['name'], 0, 200)];
}

/** Genera un código alfanumérico legible (sin caracteres ambiguos). */
function codigo_aleatorio(int $largo = 8): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $largo; $i++) $out .= $abc[random_int(0, strlen($abc) - 1)];
    return $out;
}

/** Clave de licencia con el formato VCP-XXXX-XXXX-XXXX-XXXX. */
function generar_clave_licencia(string $plan = 'escuela'): string {
    $letra = ['personal' => 'P', 'escuela' => 'E', 'sitio' => 'S'][$plan] ?? 'E';
    $b = [];
    for ($i = 0; $i < 4; $i++) $b[] = codigo_aleatorio(4);
    $b[0] = $letra . substr($b[0], 1);
    return 'VCP-' . implode('-', $b);
}

/** Envía un CSV al navegador. */
function descargar_csv(string $nombre, array $encabezados, array $filas): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
    fputcsv($out, $encabezados, ';');
    foreach ($filas as $f) fputcsv($out, $f, ';');
    fclose($out);
    exit;
}
