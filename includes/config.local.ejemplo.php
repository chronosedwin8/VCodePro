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

// -------------------------------------------------------- Phidias (opcional)
// Integración con la matrícula del colegio. También puede configurarse desde
// Ajustes del portal; lo definido aquí tiene prioridad.
// define('PHIDIAS_URL',   'https://ds-barranquilla.phidias.co/rest');
// define('PHIDIAS_TOKEN', 'pega-aqui-el-token-jwt');

// --------------------------------------------- Mercado Pago (opcional) -----
// Igual que Phidias: también se pueden guardar desde Ajustes del portal, pero
// lo definido aquí tiene prioridad y no viaja en el repositorio.
// define('MERCADOPAGO_PUBLIC_KEY',     'APP_USR-…');
// define('MERCADOPAGO_ACCESS_TOKEN',   'APP_USR-…');   // TEST-… en el entorno de pruebas
// define('MERCADOPAGO_WEBHOOK_SECRET', '…');           // la genera el panel al registrar la URL
