<?php
/**
 * Conexión PDO y ayudas de consulta.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PUERTO, DB_NOMBRE);
    try {
        $pdo = new PDO($dsn, DB_USUARIO, DB_CLAVE, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        if (APP_ENTORNO === 'desarrollo') {
            http_response_code(500);
            exit('<h1>Sin conexión a la base de datos</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>'
               . '<p>Revisa <code>includes/config.php</code> o ejecuta <a href="' . BASE_URL . '/instalar.php">instalar.php</a>.</p>');
        }
        http_response_code(503);
        exit('Servicio no disponible.');
    }
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Primera fila o null. */
function fila(string $sql, array $params = []): ?array {
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

/** Todas las filas. */
function filas(string $sql, array $params = []): array {
    return q($sql, $params)->fetchAll();
}

/** Primer valor de la primera fila. */
function valor(string $sql, array $params = [], $porDefecto = null) {
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $porDefecto : $v;
}

/** INSERT sencillo a partir de un arreglo asociativo. Devuelve el id. */
function insertar(string $tabla, array $datos): int {
    $cols = array_keys($datos);
    $sql  = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $tabla,
        implode('`, `', $cols),
        implode(', ', array_map(fn($c) => ':' . $c, $cols))
    );
    q($sql, $datos);
    return (int) db()->lastInsertId();
}

/** UPDATE sencillo por id. Devuelve las filas afectadas. */
function actualizar(string $tabla, array $datos, string $donde, array $params = []): int {
    $sets = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($datos)));
    $sql  = "UPDATE `$tabla` SET $sets WHERE $donde";
    return q($sql, array_merge($datos, $params))->rowCount();
}

/** DELETE sencillo. */
function borrar(string $tabla, string $donde, array $params = []): int {
    return q("DELETE FROM `$tabla` WHERE $donde", $params)->rowCount();
}

/** Lee un ajuste del sistema. */
function ajuste(string $clave, $porDefecto = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (filas('SELECT clave, valor FROM ajustes') as $r) {
                $cache[$r['clave']] = $r['valor'];
            }
        } catch (Throwable $e) { $cache = []; }
    }
    return $cache[$clave] ?? $porDefecto;
}

function guardar_ajuste(string $clave, string $valor): void {
    q('INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$clave, $valor]);
}
