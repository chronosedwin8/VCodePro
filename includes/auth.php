<?php
/**
 * Autenticación, sesiones persistentes y control de acceso por rol.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const ROLES = ['admin' => 'Administrador', 'docente' => 'Docente', 'estudiante' => 'Estudiante', 'cliente' => 'Cliente'];

/** Panel de inicio de cada rol. */
function panel_de(string $rol): string {
    return match ($rol) {
        'admin'      => 'portal/admin/',
        'docente'    => 'portal/docente/',
        'cliente'    => 'portal/cliente/',
        default      => 'portal/estudiante/',
    };
}

// ------------------------------------------------------------------ acceso --

function autenticar(string $email, string $clave, bool $recordar = false): array {
    iniciar_sesion();
    $email = mb_strtolower(trim($email));
    $u = fila('SELECT * FROM usuarios WHERE email = ?', [$email]);

    if (!$u) {
        usleep(300000); // el tiempo de respuesta no debe revelar si el correo existe
        return [false, 'Correo o contraseña incorrectos.'];
    }
    if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        $min = max(1, (int) ceil((strtotime($u['bloqueado_hasta']) - time()) / 60));
        return [false, "Cuenta bloqueada temporalmente. Intenta de nuevo en $min minutos."];
    }
    if (!password_verify($clave, $u['password_hash'])) {
        $intentos = (int) $u['intentos'] + 1;
        $bloqueo  = $intentos >= MAX_INTENTOS ? date('Y-m-d H:i:s', time() + BLOQUEO_MINUTOS * 60) : null;
        actualizar('usuarios', ['intentos' => $intentos, 'bloqueado_hasta' => $bloqueo], 'id = :id', ['id' => $u['id']]);
        auditar('login_fallido', 'usuarios', (int) $u['id'], $email);
        return [false, 'Correo o contraseña incorrectos.'];
    }
    if ($u['estado'] === 'suspendido') {
        return [false, 'Tu cuenta está suspendida. Contacta al administrador del colegio.'];
    }
    if ($u['estado'] === 'pendiente') {
        return [false, 'Tu cuenta todavía no ha sido aprobada por un administrador.'];
    }

    // Rehash si el algoritmo por defecto cambió.
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        actualizar('usuarios', ['password_hash' => password_hash($clave, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
    }

    abrir_sesion_usuario($u);
    if ($recordar) crear_recordatorio((int) $u['id']);

    return [true, $u];
}

function abrir_sesion_usuario(array $u): void {
    iniciar_sesion();
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $u['id'];
    $_SESSION['rol']        = $u['rol'];
    $_SESSION['nombre']     = $u['nombre'];
    $_SESSION['creada']     = time();

    actualizar('usuarios', [
        'ultimo_acceso'   => date('Y-m-d H:i:s'),
        'intentos'        => 0,
        'bloqueado_hasta' => null,
    ], 'id = :id', ['id' => $u['id']]);

    auditar('login', 'usuarios', (int) $u['id']);
}

function cerrar_sesion(): void {
    iniciar_sesion();
    if (!empty($_SESSION['usuario_id'])) auditar('logout', 'usuarios', (int) $_SESSION['usuario_id']);
    borrar_recordatorio();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

// ------------------------------------------------------- recordar sesión ---

function crear_recordatorio(int $usuarioId): void {
    $selector  = bin2hex(random_bytes(12));
    $validador = bin2hex(random_bytes(32));
    insertar('recordatorios_sesion', [
        'usuario_id' => $usuarioId,
        'selector'   => $selector,
        'validador'  => hash('sha256', $validador),
        'expira_en'  => date('Y-m-d H:i:s', time() + RECORDAR_VIDA),
    ]);
    setcookie('vcp_recordar', $selector . ':' . $validador, [
        'expires'  => time() + RECORDAR_VIDA,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

function borrar_recordatorio(): void {
    if (!empty($_COOKIE['vcp_recordar'])) {
        [$sel] = array_pad(explode(':', $_COOKIE['vcp_recordar'], 2), 2, '');
        if ($sel !== '') borrar('recordatorios_sesion', 'selector = ?', [$sel]);
        setcookie('vcp_recordar', '', time() - 42000, BASE_URL === '' ? '/' : BASE_URL . '/');
    }
}

function intentar_recordar(): void {
    if (!empty($_SESSION['usuario_id']) || empty($_COOKIE['vcp_recordar'])) return;
    [$sel, $val] = array_pad(explode(':', $_COOKIE['vcp_recordar'], 2), 2, '');
    if ($sel === '' || $val === '') return;

    $r = fila('SELECT * FROM recordatorios_sesion WHERE selector = ? AND expira_en > NOW()', [$sel]);
    if (!$r || !hash_equals($r['validador'], hash('sha256', $val))) { borrar_recordatorio(); return; }

    $u = fila('SELECT * FROM usuarios WHERE id = ? AND estado = "activo"', [$r['usuario_id']]);
    if ($u) abrir_sesion_usuario($u);
}

// ------------------------------------------------------------ usuario actual --

function usuario(): ?array {
    static $cache = null;
    iniciar_sesion();
    intentar_recordar();
    if (empty($_SESSION['usuario_id'])) return null;
    if ($cache !== null && $cache['id'] === $_SESSION['usuario_id']) return $cache;

    $u = fila('SELECT u.*, c.nombre AS colegio_nombre
                 FROM usuarios u LEFT JOIN colegios c ON c.id = u.colegio_id
                WHERE u.id = ?', [$_SESSION['usuario_id']]);
    if (!$u || $u['estado'] === 'suspendido') { cerrar_sesion(); return null; }
    $u['id'] = (int) $u['id'];
    return $cache = $u;
}

function uid(): int {
    $u = usuario();
    return $u ? (int) $u['id'] : 0;
}

function rol(): string {
    $u = usuario();
    return $u ? $u['rol'] : '';
}

function es(string ...$roles): bool {
    return in_array(rol(), $roles, true);
}

function nombre_completo(?array $u = null): string {
    $u ??= usuario();
    if (!$u) return '';
    return trim($u['nombre'] . ' ' . ($u['apellidos'] ?? ''));
}

// -------------------------------------------------------------- guardianes --

function exigir_login(): array {
    $u = usuario();
    if (!$u) {
        iniciar_sesion();
        $_SESSION['destino'] = url_actual();
        flash_err('Inicia sesión para continuar.');
        redirigir('portal/login.php');
    }
    return $u;
}

function exigir_rol(string ...$roles): array {
    $u = exigir_login();
    if (!in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        auditar('acceso_denegado', null, null, url_actual());
        include APP_RAIZ . '/portal/403.php';
        exit;
    }
    return $u;
}

// ----------------------------------------------------------------- registro --

/** Valida y crea un usuario. Devuelve [ok, id|mensaje]. */
function crear_usuario(array $d): array {
    $email = mb_strtolower(trim($d['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'El correo electrónico no es válido.'];
    if (valor('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$email])) {
        return [false, 'Ya existe una cuenta con ese correo.'];
    }
    $clave = (string) ($d['clave'] ?? '');
    $err = validar_clave($clave);
    if ($err) return [false, $err];
    if (trim($d['nombre'] ?? '') === '') return [false, 'El nombre es obligatorio.'];

    $id = insertar('usuarios', [
        'colegio_id'    => $d['colegio_id'] ?? null,
        'nombre'        => trim($d['nombre']),
        'apellidos'     => trim($d['apellidos'] ?? ''),
        'email'         => $email,
        'password_hash' => password_hash($clave, PASSWORD_DEFAULT),
        'rol'           => in_array($d['rol'] ?? '', array_keys(ROLES), true) ? $d['rol'] : 'estudiante',
        'estado'        => $d['estado'] ?? 'activo',
        'documento'     => $d['documento'] ?? null,
        'telefono'      => $d['telefono'] ?? null,
        'cargo'         => $d['cargo'] ?? null,
    ]);
    auditar('usuario_creado', 'usuarios', $id, $email);
    return [true, $id];
}

function validar_clave(string $c): ?string {
    if (mb_strlen($c) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-z]/', $c) || !preg_match('/\d/', $c)) {
        return 'La contraseña debe combinar letras y números.';
    }
    return null;
}

function cambiar_clave(int $usuarioId, string $nueva): void {
    actualizar('usuarios', ['password_hash' => password_hash($nueva, PASSWORD_DEFAULT)], 'id = :id', ['id' => $usuarioId]);
    borrar('recordatorios_sesion', 'usuario_id = ?', [$usuarioId]);
    auditar('clave_cambiada', 'usuarios', $usuarioId);
}
