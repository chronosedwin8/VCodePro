<?php
/**
 * Guarda la preferencia de tema del usuario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

$u = usuario();
if (!$u || !es_post()) json_salida(['ok' => false], 400);

$tema = post('tema') === 'light' ? 'light' : 'dark';
actualizar('usuarios', ['tema' => $tema], 'id = :id', ['id' => $u['id']]);

json_salida(['ok' => true, 'tema' => $tema]);
