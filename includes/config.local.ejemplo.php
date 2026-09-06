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

// ------------------------------- Adjuntos en Amazon S3 (opcional) ----------
// Archivos que los estudiantes adjuntan a sus entregas. Si no se configura,
// se guardan en el disco del servidor (assets/uploads/entregas).
//
// El bucket debe ser PRIVADO: el portal entrega cada archivo con un enlace
// firmado que caduca en minutos. Si el bucket permite lectura pública, el
// trabajo de los estudiantes queda accesible para cualquiera que sepa la URL.
//
// El usuario IAM solo necesita s3:PutObject, s3:GetObject y s3:DeleteObject
// sobre arn:aws:s3:::TU-BUCKET/*
// define('S3_BUCKET',  'tu-bucket');
// define('S3_REGION',  'us-east-1');
// define('S3_LLAVE',   'AKIA…');
// define('S3_SECRETO', '…');
// define('S3_PREFIJO', 'vcodepro');   // carpeta dentro del bucket

// ----------------------------------- Asistente de IA (Google) — opcional --
// Calificación asistida de entregas y redacción de actividades.
//
// Tener la clave NO habilita a nadie: el permiso se concede docente por
// docente desde Usuarios, en el panel de administración. Las notas que
// propone quedan marcadas como propuesta y no las ve el estudiante hasta que
// el docente las publica.
//
// La clave se saca de https://aistudio.google.com/apikey
// define('IA_CLAVE',  'AIza…');
// define('IA_MODELO', 'gemini-3.8-flash');

// ------------------------- Ingreso con Microsoft (Entra ID) — opcional ----
// Permite entrar con la cuenta institucional. Los dos identificadores están
// en Azure → App registrations → tu aplicación → Overview.
//
// El secreto se crea en Certificates & secrets → New client secret: se copia
// el **Value** (no el Secret ID) y solo se ve una vez. Caduca, así que anota
// la fecha y renuévalo antes.
//
// La dirección de retorno que hay que registrar en Azure (Authentication →
// Redirect URIs, plataforma Web) la muestra el propio portal en
// Ajustes → Ingreso con Microsoft.
// define('ENTRA_CLIENTE', '00000000-0000-0000-0000-000000000000');
// define('ENTRA_TENANT',  '00000000-0000-0000-0000-000000000000');
// define('ENTRA_SECRETO', '…');
// define('ENTRA_DOMINIOS', 'colegioaleman.edu.co, estudiantes.colegioaleman.edu.co');
