<?php
/**
 * VCodePro · Portal académico
 * Configuración global de la aplicación.
 */

declare(strict_types=1);

/**
 * Credenciales del servidor. Si existe includes/config.local.php se carga
 * primero: allí van las constantes de producción, fuera del repositorio.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ---------------------------------------------------------------- entorno --
define('APP_NOMBRE', 'VCodePro');
define('APP_LEMA',   'Portal académico IB');
define('APP_ENTORNO', getenv('VCP_ENV') ?: 'desarrollo');   // desarrollo | produccion
define('APP_RAIZ', dirname(__DIR__));

// ------------------------------------------------------------ base de datos --
defined('DB_HOST')    || define('DB_HOST', getenv('VCP_DB_HOST') ?: '127.0.0.1');
defined('DB_PUERTO')  || define('DB_PUERTO', getenv('VCP_DB_PORT') ?: '3306');
defined('DB_NOMBRE')  || define('DB_NOMBRE', getenv('VCP_DB_NAME') ?: 'vcodepro');
defined('DB_USUARIO') || define('DB_USUARIO', getenv('VCP_DB_USER') ?: 'root');
// La contraseña nunca se escribe aquí: llega de includes/config.local.php o de
// la variable de entorno VCP_DB_PASS.
defined('DB_CLAVE')   || define('DB_CLAVE', getenv('VCP_DB_PASS') !== false ? getenv('VCP_DB_PASS') : '');

// ------------------------------------------------------------------- rutas --
/**
 * Calcula el prefijo de URL de la aplicación. En XAMPP el proyecto vive en
 * /vcodeproplus; en el servidor con CloudPanel + nginx vive en la raíz del
 * dominio y el prefijo queda vacío.
 */
function vcp_base_url(): string {
    static $base = null;
    if ($base !== null) return $base;

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_RAIZ);
    if ($docRoot && $appRoot && str_starts_with(str_replace('\\', '/', $appRoot), str_replace('\\', '/', $docRoot))) {
        $rel = substr(str_replace('\\', '/', $appRoot), strlen(str_replace('\\', '/', $docRoot)));
        $base = rtrim($rel, '/');
    } else {
        $base = '';
    }
    return $base;
}

define('BASE_URL', vcp_base_url());
define('RUTA_SUBIDAS', APP_RAIZ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
define('URL_SUBIDAS', BASE_URL . '/assets/uploads');

// ---------------------------------------------------------------- seguridad --
define('SESION_NOMBRE', 'vcp_portal');
define('SESION_VIDA', 60 * 60 * 8);          // 8 horas
define('RECORDAR_VIDA', 60 * 60 * 24 * 30);  // 30 días
define('MAX_INTENTOS', 6);
define('BLOQUEO_MINUTOS', 15);
define('SUBIDA_MAX_BYTES', 12 * 1024 * 1024);
define('SUBIDA_EXTENSIONES', ['pdf','zip','py','js','html','css','txt','md','png','jpg','jpeg','gif','json','csv','ipynb','sql','java','cpp','c']);

// ------------------------------------------------------------- adjuntos ---
// Lo que un estudiante puede adjuntar a una entrega, por familias. Es una
// lista blanca: nada de .exe, .bat, .js suelto ni nada ejecutable.
define('ADJUNTO_MAX_BYTES', 25 * 1024 * 1024);
define('ADJUNTO_FAMILIAS', [
    'Documentos'       => ['pdf','doc','docx','odt','rtf','txt','md'],
    'Hojas de cálculo' => ['xls','xlsx','ods','csv','tsv'],
    'Presentaciones'   => ['ppt','pptx','odp'],
    'Python y código'  => ['py','ipynb','json','sql','java','c','cpp','h','html','css','xml','yml','yaml'],
    'Comprimidos'      => ['zip','rar','7z','tar','gz','tgz'],
    'Imágenes'         => ['png','jpg','jpeg','gif','webp','svg'],
]);

// --------------------------------------------------------------- pedagogía --
define('FASES_CICLO', [
    'indagar'     => 'Indagar y analizar',
    'desarrollar' => 'Desarrollar ideas',
    'crear'       => 'Crear la solución',
    'evaluar'     => 'Evaluar',
]);

define('CRITERIOS_IB', [
    'A' => 'Indagación y análisis',
    'B' => 'Desarrollo de ideas',
    'C' => 'Creación de la solución',
    'D' => 'Evaluación',
]);

define('CONTEXTOS_GLOBALES', [
    'Identidades y relaciones',
    'Orientación en el espacio y el tiempo',
    'Expresión personal y cultural',
    'Innovación científica y técnica',
    'Globalización y sustentabilidad',
    'Equidad y desarrollo',
]);

define('PERFIL_IB', [
    'Indagador','Informado e instruido','Pensador','Buen comunicador','Íntegro',
    'De mentalidad abierta','Solidario','Audaz','Equilibrado','Reflexivo',
]);

define('ATL', [
    'Pensamiento crítico','Pensamiento creativo','Transferencia','Organización',
    'Colaboración','Alfabetización mediática','Reflexión','Autogestión',
    'Alfabetización informacional','Comunicación',
]);

// --------------------------------------------------------------- errores ---
if (APP_ENTORNO === 'desarrollo') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('America/Bogota');
