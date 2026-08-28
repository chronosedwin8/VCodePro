<?php
/**
 * Plantilla de configuración del servidor.
 *
 * Copia este archivo como includes/config.local.php en el servidor y ajusta
 * los valores. No se versiona: así las credenciales de producción nunca
 * viajan en el repositorio.
 */

declare(strict_types=1);

define('DB_HOST',    '127.0.0.1');
define('DB_PUERTO',  '3306');
define('DB_NOMBRE',  'vcodepro');
define('DB_USUARIO', 'vcodepro_app');
define('DB_CLAVE',   'cambia-esta-clave');

// Entorno: 'produccion' oculta los mensajes de error al visitante.
putenv('VCP_ENV=produccion');
