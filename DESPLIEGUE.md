# Despliegue en CloudPanel + Nginx

Guía del despliegue inicial de **www.vcodepro.de**. El proyecto no necesita compilación ni
gestor de paquetes: es PHP 8.2 y MySQL 8 servidos como archivos reales.

> **Lo más importante de esta guía.** El repositorio trae tres archivos `.htaccess` que protegen
> `includes/`, `db/` y `assets/uploads/`. **En Nginx no hacen absolutamente nada.** Sin los
> bloques del paso 5, `https://www.vcodepro.de/db/schema.sql` se descarga desde el navegador.
> Está comprobado: sirviendo el proyecto sin Apache, esa ruta responde 200.

---

## 1. Crear el sitio en CloudPanel

**Sites → Add Site → Create a PHP Site**

| Campo | Valor |
|---|---|
| Domain Name | `www.vcodepro.de` |
| PHP Version | 8.2 u 8.3 |
| Site User | por ejemplo `vcodepro` |
| App / Vhost Template | PHP genérico (no WordPress ni Laravel) |

El directorio raíz queda en `/home/vcodepro/htdocs/www.vcodepro.de`.

Añade también el dominio sin `www` y redirígelo a `www`: todas las URL canónicas, el Open Graph
y el `sitemap.xml` del sitio apuntan a `https://www.vcodepro.de/`.

---

## 2. Base de datos

**Databases → Add Database**

| Campo | Valor |
|---|---|
| Database Name | `vcodepro` |
| User Name | `vcodepro_app` |
| Password | genérala con CloudPanel y guárdala |

Anótala: va en el archivo del paso 4. No uses `root`.

---

## 3. Subir el código

Conéctate por SSH **como el usuario del sitio** (no como root: si subes archivos con otro
usuario, PHP-FPM no podrá escribir en `assets/uploads/`).

```bash
cd /home/vcodepro/htdocs/www.vcodepro.de
rm -f index.html                      # el placeholder que crea CloudPanel
git clone https://github.com/chronosedwin8/VCodePro .
```

Si prefieres no usar Git en el servidor, sube el contenido por SFTP con ese mismo usuario.

Permisos de escritura solo donde hacen falta:

```bash
chmod -R 775 assets/uploads
```

---

## 4. Credenciales del servidor

Este archivo **no está en el repositorio** y sin él la aplicación no conecta.

```bash
cp includes/config.local.ejemplo.php includes/config.local.php
nano includes/config.local.php
```

```php
<?php
declare(strict_types=1);

define('DB_HOST',    '127.0.0.1');
define('DB_PUERTO',  '3306');
define('DB_NOMBRE',  'vcodepro');
define('DB_USUARIO', 'vcodepro_app');
define('DB_CLAVE',   'la-que-generó-cloudpanel');

// Oculta los mensajes de error al visitante.
putenv('VCP_ENV=produccion');

// Integración con la matrícula del colegio.
define('PHIDIAS_URL',   'https://ds-barranquilla.phidias.co/rest');
define('PHIDIAS_TOKEN', 'el-token-jwt');
```

```bash
chmod 600 includes/config.local.php
```

`VCP_ENV=produccion` se lee después de cargar este archivo, así que basta con definirlo aquí.

---

## 5. Vhost de Nginx

**Sites → www.vcodepro.de → Vhost.** Estos bloques sustituyen a los `.htaccess`.

```nginx
# El sitio público arranca en index.html, no en index.php
index index.html index.php;

# Subidas de hasta 12 MB (el límite del portal) con margen
client_max_body_size 30M;

# Cada archivo es real: no enrutar los 404 hacia index.php
location / {
    try_files $uri $uri/ =404;
}

# --- Código y datos internos: nunca se sirven -------------------------------
# ^~ tiene prioridad sobre el bloque regex de PHP, así que /includes/config.php
# se deniega en vez de ejecutarse o filtrarse.
location ^~ /includes/     { deny all; return 404; }
location ^~ /db/           { deny all; return 404; }
location = /instalar.php   { deny all; return 404; }
location ~ /\.(git|env)    { deny all; return 404; }
location = /.gitignore     { deny all; return 404; }

# --- Archivos subidos por los estudiantes ----------------------------------
# No se ejecuta nada y se fuerza la descarga: un .html subido no puede
# ejecutar JavaScript en el dominio del portal.
location ^~ /assets/uploads/ {
    location ~ \.(php|phtml|phar|cgi|pl)$ { deny all; return 404; }
    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Disposition "attachment" always;
}

# --- Cabeceras de seguridad ------------------------------------------------
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

Notas:

- El portal enlaza los archivos subidos siempre como `<a href>`, nunca como `<img src>`, así que
  forzar la descarga no rompe ninguna vista. Está comprobado en el código.
- Deja el bloque `location ~ \.php$` con `fastcgi_pass` que CloudPanel ya generó: no lo toques.
- **Después de cambiar la versión de PHP o cualquier ajuste del sitio, vuelve a revisar el
  vhost**: CloudPanel puede regenerarlo y perder estas líneas.

Aplica y comprueba:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Ajustes de PHP

**Sites → www.vcodepro.de → PHP Settings**

| Ajuste | Valor | Por qué |
|---|---|---|
| `upload_max_filesize` | `30M` | Los adjuntos de las entregas llegan a 25 MB |
| `post_max_size` | `30M` | Debe superar al anterior |
| `memory_limit` | `256M` | El consolidado de Phidias ocupa 12 MB al procesarse; el resto es margen |
| `max_execution_time` | `120` | Importar un curso completo crea una cuenta por estudiante y cada `password_hash` tarda unas décimas de segundo |

Con los 30 s por defecto, importar 50 estudiantes de golpe puede cortarse a mitad. La zona
horaria la fija el propio código (`America/Bogota`), no hay que tocarla.

---

## 7. Instalar

Desde el directorio raíz del sitio y con el usuario del sitio:

```bash
php instalar.php --sin-demo "UnaClaveFuerteParaElAdministrador"
```

`--sin-demo` crea el esquema, los niveles, el banco de actividades, las insignias, los ajustes y
tu cuenta de administración, **pero ningún dato de ejemplo**. Es lo que quieres en un servidor
con matrícula real: te ahorra el paso 9 entero. Sin ese modificador se crean además 2 docentes,
24 estudiantes, un cliente, una licencia y entregas de muestra.

Debe terminar con las 31 tablas, los 7 niveles y las 133 actividades. Después:

```bash
rm instalar.php
```

El bloque `location = /instalar.php` del paso 5 es el cinturón por si algún día vuelve a
aparecer el archivo tras un `git pull`.

---

## 8. HTTPS

**Sites → www.vcodepro.de → SSL/TLS → Let's Encrypt**, y activa la redirección de HTTP a HTTPS.

Comprueba que PHP recibe la señal de HTTPS, porque de eso depende que la cookie de sesión salga
marcada como `Secure`:

```bash
# Debe imprimir: on
curl -s https://www.vcodepro.de/portal/login.php -o /dev/null -D - | grep -i "set-cookie"
# La cookie vcp_portal debe incluir Secure y HttpOnly
```

Si no aparece `Secure`, añade al vhost del bloque SSL:

```nginx
fastcgi_param HTTPS on;
```

---

## 9. Limpiar los datos de demostración

> Si instalaste con `--sin-demo`, **sáltate este paso**: no hay nada que limpiar.

El instalador crea un colegio de ejemplo con 2 docentes, 24 estudiantes, un cliente, una
licencia, dos facturas y un ticket. **Antes de abrir el portal a personas reales**, haz una copia
de seguridad y elimínalos:

```sql
-- Copia de seguridad primero:  mysqldump -u vcodepro_app -p vcodepro > respaldo.sql

DELETE FROM usuarios WHERE email IN (
  'docente.diseno@colegioaleman.edu.co',
  'docente.informatica@colegioaleman.edu.co',
  'rectoria@colegioaleman.edu.co'
);
DELETE FROM usuarios WHERE email LIKE '%@estudiantes.colegioaleman.edu.co';
DELETE FROM mensajes_contacto;
DELETE FROM licencias;          -- borra también sus puestos y deja las facturas huérfanas
DELETE FROM facturas;
DELETE FROM tickets;
```

Las cascadas de la base de datos se llevan sus grupos, asignaciones y entregas. El
administrador, los 7 niveles y las 133 actividades no se tocan.

Comprueba después que solo queda tu cuenta:

```sql
SELECT id, email, rol FROM usuarios;
```

---

## 10. Importar la matrícula real

Entra como administrador y ve a **Docente → Importar de Phidias**.

- Empieza con **un curso** para ver el resultado antes de hacerlo masivo.
- Importa **curso por curso**, no los 52 de una vez: son 1.176 estudiantes y cada cuenta nueva
  cuesta un `password_hash`.
- **Descarga el CSV de credenciales al terminar cada importación.** Es la única oportunidad: las
  contraseñas no se guardan en texto plano.
- Entrega esas credenciales por un canal seguro y pide el cambio en el primer ingreso.

---

## 10 bis. Activar el cobro en línea

1. En el panel de Mercado Pago, cambia el **producto integrado de tu aplicación a
   Checkout Pro**. Hoy figura como *Checkout API*, que es la modalidad que obliga a
   PCI DSS SAQ A-EP; el portal ya no la usa.
2. **Rota las credenciales de producción** si alguna vez las compartiste por chat o correo.
3. En **Admin → Ajustes → Pasarela de pagos** pega el Access Token, elige el modo y guarda.
4. Copia de ese panel la **URL del webhook** y regístrala en Mercado Pago (modo de prueba y
   modo productivo). Pega la clave secreta que genera al guardarla.
5. Deja marcados solo los eventos que aplican: **Order**, **Contracargos** y **Reclamos**.
   *Envíos*, *Card Updater*, *Point*, *Delivery* y *Wallet Connect* no aplican a un servicio
   digital y solo generan ruido.
6. Haz **una compra real de bajo monto** en modo producción y compruébala de punta a punta:
   factura pagada, licencia activa y notificación recibida en Admin → Ajustes.

La URL de retorno no hay que registrarla en ningún sitio: el portal la envía en cada
preferencia. Sí debe ser **pública y con HTTPS**, o el retorno automático no funcionará.

---

## 10 ter. Adjuntos en Amazon S3

Los estudiantes adjuntan documentos, hojas de cálculo, cuadernos de Python, comprimidos y PDF
a sus entregas. Sin configurar nada, esos archivos se guardan en `assets/uploads/entregas`
dentro del servidor. Con S3 configurado van al bucket, que es lo recomendable: no ocupan disco
del VPS y sobreviven a una reinstalación.

**El bucket tiene que ser privado.** Es trabajo escolar de menores de edad: el portal entrega
cada archivo con un enlace firmado que caduca a los cinco minutos, y solo a quien tiene permiso
—el dueño de la entrega, su docente y la administración—. Si el bucket permite lectura pública,
ese cuidado no sirve de nada porque basta con conocer la URL.

Comprueba que está cerrado:

```bash
# Debe responder 403. Si responde 200, el bucket es público.
curl -s -o /dev/null -w '%{http_code}\n' https://TU-BUCKET.s3.amazonaws.com/
```

En la consola de AWS: **S3 → tu bucket → Permissions → Block public access → Edit → marcar las
cuatro casillas**, y borrar cualquier *bucket policy* que conceda `s3:GetObject` a `Principal: *`.

El usuario IAM solo necesita esto sobre el bucket, nada más:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
    "Resource": "arn:aws:s3:::TU-BUCKET/*"
  }]
}
```

Las credenciales van en `includes/config.local.php` (ver el paso 4) o, si prefieres no tocar
archivos, en **Ajustes → Adjuntos de las entregas**, donde además hay un botón **Probar
almacenamiento** que sube, lee y borra un objeto de diagnóstico y te dice qué falló si algo
falla.

Recuerda que el tamaño máximo real es el menor de tres números: `ADJUNTO_MAX_BYTES` (25 MB),
`upload_max_filesize` de PHP y `client_max_body_size` de Nginx. Los pasos 5 y 6 ya los dejan
en 30 MB.

---
## 11. Verificación final

```bash
# El sitio público responde
curl -sI https://www.vcodepro.de/ | head -1

# El portal responde
curl -sI https://www.vcodepro.de/portal/login.php | head -1

# El código NO se sirve: las tres deben dar 404
curl -so /dev/null -w "%{http_code} db/schema.sql\n"        https://www.vcodepro.de/db/schema.sql
curl -so /dev/null -w "%{http_code} includes/config.php\n"  https://www.vcodepro.de/includes/config.php
curl -so /dev/null -w "%{http_code} instalar.php\n"         https://www.vcodepro.de/instalar.php

# El sitemap y el robots apuntan al dominio correcto
curl -s https://www.vcodepro.de/robots.txt

# El webhook responde
curl -s https://www.vcodepro.de/portal/api/mercadopago.php
```

Y en el navegador:

- Entrar como administrador y abrir **Ajustes**: el bloque «Estado del sistema» debe mostrar
  `Ruta base: /` y la carpeta de subidas con permisos de escritura.
- Pulsar **Probar conexión** en «Conexión con Phidias».
- Crear un grupo, asignar una actividad y adjuntar un archivo desde una cuenta de estudiante:
  eso ejercita subida, permisos y límites de tamaño de una sola vez.

---

## 12. Copias de seguridad

Dos cosas hay que respaldar, y una de ellas no está en Git:

| Qué | Dónde | Por qué |
|---|---|---|
| Base de datos `vcodepro` | CloudPanel → Backups, o `mysqldump` diario | Todo el trabajo académico |
| `assets/uploads/` | rsync o el backup de CloudPanel | Los archivos entregados por los estudiantes |

El código se recupera del repositorio; `includes/config.local.php` hay que guardarlo aparte
(gestor de contraseñas), porque tampoco está versionado.

---

## 13. Actualizaciones posteriores

```bash
cd /home/vcodepro/htdocs/www.vcodepro.de
git pull
php instalar.php --sin-demo   # idempotente: aplica migraciones y actualiza el banco
rm -f instalar.php
```

**Siempre con `--sin-demo`.** Sin él, cada actualización volvería a sembrar los docentes,
estudiantes y grupos de ejemplo dentro de tu matrícula real.

El instalador no duplica nada y no toca la contraseña de una cuenta que ya existe. Si una
versión añade columnas nuevas, las aplica en el paso de migraciones. Las fases de las
actividades y los criterios de rúbrica se actualizan en su sitio, sin borrarlos: de lo
contrario las cascadas de la base de datos se llevarían por delante el trabajo escrito de los
estudiantes y sus calificaciones.

---

## Resumen de lo que suele fallar

| Síntoma | Causa |
|---|---|
| «Sin conexión a la base de datos» | Falta `includes/config.local.php` o la contraseña no es la de CloudPanel |
| `db/schema.sql` se descarga | Faltan los bloques `location ^~` del paso 5 |
| Los adjuntos de más de 1 MB fallan | `client_max_body_size` por defecto en Nginx |
| La importación de Phidias se corta | `max_execution_time` de 30 s |
| La sesión se cierra al cambiar de página | PHP no recibe `HTTPS on` y la cookie `Secure` no viaja |
| Las páginas del portal dan 404 | `try_files` enrutando hacia un `index.php` que no existe |
