# VCodePro — sitio web y portal académico

Sitio de **VCodePro** (editor de código con IA para enseñar programación en colegios) más el
**portal académico IB** que lo acompaña: usuarios, grupos, banco de actividades del ciclo de
diseño, entregas, rúbricas, calificación, licencias y soporte.

- **Sitio público:** HTML5, CSS3 y JavaScript puro, sin dependencias ni compilación.
- **Portal académico:** PHP 8.2 y MySQL 8, sin frameworks ni gestor de paquetes.

---

## Puesta en marcha

```
1. MySQL en marcha (usuario con permisos para crear la base de datos).
2. Copia includes/config.local.ejemplo.php a includes/config.local.php
   y escribe allí las credenciales. Ese archivo no se versiona.
3. php instalar.php "TuClaveDeAdministrador"
   (o abre http://localhost:8080/vcodeproplus/instalar.php)
4. Entra en http://localhost:8080/vcodeproplus/portal/login.php
5. Borra o renombra instalar.php.
```

El repositorio no contiene ninguna contraseña. La de la base de datos llega de
`includes/config.local.php` o de la variable `VCP_DB_PASS`; la del administrador
inicial, del primer argumento de `instalar.php` o de `VCP_ADMIN_PASS`. Si no se
indica ninguna, el instalador **genera una al azar y la muestra al terminar**.

El instalador crea la base de datos, aplica el esquema, carga los siete niveles con sus **84 actividades**, las insignias, el administrador inicial y un colegio de demostración. Es
idempotente: puede ejecutarse varias veces sin duplicar datos.

### Cuentas creadas por el instalador

| Rol | Correo | Contraseña |
|-----|--------|------------|
| Administrador | `eortiz@colegioaleman.edu.co` | la que indiques al instalar |
| Docente (Diseño) | `docente.diseno@colegioaleman.edu.co` | `Docente2026*` |
| Docente (Informática) | `docente.informatica@colegioaleman.edu.co` | `Docente2026*` |
| Estudiante | `mariana.acosta@estudiantes.colegioaleman.edu.co` | `Estudiante2026*` |
| Cliente | `rectoria@colegioaleman.edu.co` | `Cliente2026*` |

Cambia las contraseñas de demostración antes de publicar el portal.

### Cuenta para recorrer el banco completo

```
php db/estudiante_demo.php [correo] [contraseña]
```

Crea un estudiante matriculado en un grupo por cada nivel, con **las 84 actividades asignadas**,
para revisar el banco tal como lo ve un estudiante. Sin argumentos usa
`tic@colegioaleman.edu.co`. Si la cuenta ya existe y no indicas contraseña, conserva la que
tenía. El script es idempotente: al repetirlo solo agrega lo que falte.
Para retirar el recorrido basta con archivar o eliminar los grupos llamados
«Recorrido *N*.º · plan completo» desde el panel de administración.

---

## Credenciales y archivos que no se versionan

El repositorio guarda código, no configuración. **Ninguna contraseña viaja en Git**, tampoco en
el historial: un clon recién descargado no arranca hasta que le des sus propias credenciales, y
eso es intencional.

### De dónde sale cada credencial

| Credencial | De dónde la toma el código | Si no la indicas |
|---|---|---|
| Contraseña de MySQL | `includes/config.local.php` o la variable `VCP_DB_PASS` | La conexión falla con un mensaje que remite a este archivo |
| Servidor, base de datos y usuario | Los mismos dos lugares | Usa `127.0.0.1`, `vcodepro` y `root` |
| Contraseña del administrador inicial | `VCP_ADMIN_PASS` o el primer argumento de `instalar.php` | El instalador **genera una al azar y la muestra al terminar** |
| Contraseña del estudiante de recorrido | `VCP_DEMO_PASS` o el segundo argumento de `db/estudiante_demo.php` | Si la cuenta ya existe conserva la suya; si es nueva, genera una y la imprime |

`includes/config.php` solo trae valores por defecto sin secreto y carga
`includes/config.local.php` cuando existe, de modo que lo definido allí gana siempre. La
plantilla `includes/config.local.ejemplo.php` sí está en el repositorio: es el archivo que se
copia y se rellena en cada equipo.

### Archivos ignorados por Git

| Ruta | Motivo |
|---|---|
| `includes/config.local.php` | Credenciales del equipo o del servidor |
| `assets/uploads/entregas/*` | Trabajo entregado por los estudiantes |
| `assets/uploads/avatares/*` | Imágenes de perfil |

De esas dos carpetas solo se versiona un `.gitkeep`, para conservar la estructura sin subir
datos personales.

### Clonar el proyecto en otro equipo

```
git clone https://github.com/chronosedwin8/VCodePro
cd VCodePro

# Windows
copy includes\config.local.ejemplo.php includes\config.local.php
# Linux y macOS
cp includes/config.local.ejemplo.php includes/config.local.php

# edita includes/config.local.php con las credenciales de ese equipo
php instalar.php "TuClaveDeAdministrador"
```

### Lo que sí está escrito en el repositorio

Las contraseñas de las **cuentas de demostración** (`Docente2026*`, `Estudiante2026*` y
`Cliente2026*`) aparecen en `instalar.php` y en este README a propósito: son cuentas
desechables para recorrer los cuatro paneles. Cámbialas o elimina esas cuentas antes de abrir
el portal a personas reales.

---

## Estructura

```
vcodeproplus/
├── index.html … contacto.html     Sitio público (sin cambios de arquitectura)
├── instalar.php                   Instalador: esquema + currículo + datos iniciales
├── includes/
│   ├── config.php                 Configuración, rutas y vocabulario IB
│   ├── config.local.ejemplo.php   Plantilla de credenciales para el servidor
│   ├── db.php                     Conexión PDO y ayudas de consulta
│   ├── helpers.php                Escape, URLs, CSRF, flash, formatos, subidas
│   ├── auth.php                   Sesión, roles, registro y recuperación
│   ├── academico.php              Entregas, progreso, rúbrica e insignias
│   └── layout.php                 Cabecera, menú por rol y pie del portal
├── portal/
│   ├── login.php · registro.php · recuperar.php · logout.php
│   ├── perfil.php · notificaciones.php · 403.php
│   ├── estudiante/                Panel, actividades, bitácora, portafolio, insignias
│   ├── docente/                   Grupos, banco, asignaciones, seguimiento, calificación
│   ├── admin/                     Usuarios, currículo, licencias, facturación, auditoría
│   ├── cliente/                   Licencias, puestos, facturas, soporte, descargas
│   └── api/                       Guardado por fases, tema y formulario de contacto
├── db/
│   ├── schema.sql                 28 tablas InnoDB utf8mb4
│   └── seed/                      Niveles, rúbricas base, 70 actividades y 14 de uso de IA
└── assets/
    ├── css/styles.css             Sistema de diseño del sitio
    ├── css/portal.css             Capa del portal sobre los mismos tokens
    ├── js/portal.js               Tema, menú, autoguardado, filtros
    └── uploads/                   Entregas y avatares (fuera del control de versiones)
```

---

## La capa académica

### Progresión de 6.º a 12.º

| Nivel | Programa | Contenidos centrales | Proyecto insignia |
|-------|----------|----------------------|-------------------|
| 6.º | PAI 1 · Diseño | Algoritmos, variables, condicionales, ciclos, Turtle | Laberinto interactivo |
| 7.º | PAI 2 · Diseño | Listas, cadenas, aleatoriedad, diccionarios, HTML y CSS | Videojuego de plataformas |
| 8.º | PAI 3 · Diseño | Funciones, archivos, CSV, gráficas, pruebas | Estación meteorológica |
| 9.º | PAI 4 · Diseño e Informática | POO, UML, DOM, JSON, SQL, Git | App para la comunidad escolar |
| 10.º | PAI 5 · Proyecto Personal | Usuarios, modelo E-R, PHP y MySQL, seguridad, accesibilidad, despliegue | Proyecto Personal |
| 11.º | DP 1 · Informática NM y NS | Representación, arquitectura, redes, estructuras de datos, algoritmos, ML | Propuesta de la evaluación interna |
| 12.º | DP 2 · Informática NS, ITGS | Desarrollo, pruebas, optimización, documentación, ITGS, monografía | Solución final y portafolio |

**12 actividades por nivel · 84 en total** (10 del plan base y 2 de uso de asistentes de IA). Cada actividad trae:

- resumen, descripción y pregunta de indagación;
- contexto global, concepto clave, atributos del perfil y enfoques del aprendizaje;
- objetivos y entregables;
- las **cuatro fases del ciclo de diseño** con instrucciones y evidencia esperada (336 fases);
- **rúbrica con descriptores** por banda —criterios A–D del PAI en 6.º a 10.º y A–E de la
  evaluación interna del Diploma en 11.º y 12.º (360 criterios);
- recursos para el docente y código de inicio para el estudiante.

### Flujo de trabajo

```
Docente          crea grupo → comparte código → asigna del banco → sigue la matriz → califica
Estudiante       se matricula → trabaja por fases (autoguardado) → bitácora → entrega → ve la rúbrica
Coordinación     aprueba cuentas → administra el currículo → informes por nivel y por criterio
Cliente          activa la licencia → reparte puestos → facturas → soporte
```

### Calificación

Los puntajes de cada criterio se suman y se traducen a la escala IB de 1 a 7:

| Nota | Porcentaje del total |
|------|----------------------|
| 7 | 90 % o más |
| 6 | 78 – 89 % |
| 5 | 65 – 77 % |
| 4 | 50 – 64 % |
| 3 | 37 – 49 % |
| 2 | 22 – 36 % |
| 1 | menos de 22 % |

El docente puede **devolver para rehacer**: la entrega vuelve al estudiante con la
retroalimentación y se registra un intento nuevo.

### Probidad académica e IA

- Cada entrega incluye una **declaración de uso de IA** que el docente ve al calificar.
- El **modo examen** por grupo desactiva la asistencia de IA y queda registrado en la auditoría.
- Dos actividades por nivel exigen usar un asistente de propósito general (ChatGPT, Claude,
  Gemini o el que autorice el colegio) con verificación independiente, registro íntegro de las
  conversaciones y autoría marcada.
- Las demás actividades de IA son de diseño de agentes, no de consumo: el estudiante define
  propósito, alcance, reglas y prohibiciones, y mide el resultado.

---

## Seguridad

- Contraseñas con `password_hash` (bcrypt) y *rehash* automático al cambiar el algoritmo.
- Token **CSRF** en todos los formularios de escritura.
- Bloqueo temporal tras seis intentos fallidos.
- Sesión regenerada al entrar; cookies `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- «Recordarme» con esquema selector/validador (el validador se guarda con hash).
- Recuperación de contraseña con token de un solo uso, hasheado y con una hora de vigencia.
- Todas las consultas son preparadas; las entradas se limpian a UTF-8 válido.
- Subidas restringidas por extensión y tamaño, con nombre aleatorio y ejecución deshabilitada.
- `includes/` y `db/` bloqueados por `.htaccess`; auditoría de todas las acciones sensibles.
- Ninguna credencial en el repositorio: ver «Credenciales y archivos que no se versionan».

---

## Publicación en CloudPanel + nginx

El portal no necesita reescrituras: cada página es un archivo real. Basta con servir PHP y
bloquear las carpetas internas.

```nginx
server {
    server_name www.vcodepro.de;
    root /home/vcodepro/htdocs/www.vcodepro.de;
    index index.html index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Nunca servir código ni credenciales
    location ~ ^/(includes|db)/ { deny all; }
    location = /instalar.php     { deny all; }

    # Los archivos subidos no se ejecutan
    location ^~ /assets/uploads/ {
        location ~ \.php$ { deny all; }
    }
}
```

Lista de comprobación al publicar:

1. Crear `includes/config.local.php` en el servidor a partir de la plantilla, con las
   credenciales de ese servidor y `VCP_ENV=produccion`. **Sin este archivo la aplicación no
   conecta**: el repositorio no trae ninguna contraseña.
2. Crear un usuario de MySQL propio para la aplicación, no `root`, con permisos solo sobre la
   base de datos del portal.
3. Ejecutar `php instalar.php "ClaveDelAdministrador"` una vez y después eliminar el archivo.
4. Dar permiso de escritura a `assets/uploads/`.
5. Cambiar las contraseñas de demostración y las claves de licencia de ejemplo.
6. Programar la copia de seguridad de la base de datos y de `assets/uploads/`, que no está en Git.

Como el prefijo de URL se calcula solo, el mismo código funciona en
`http://localhost:8080/vcodeproplus/` y en la raíz de `https://www.vcodepro.de/`.

---

## Licenciamiento (sitio público)

| Plan | Precio | Cupo |
|------|--------|------|
| Personal | 150.000 COP al mes | 1 licencia |
| Escuela | 5.000.000 COP al año | hasta 100 |
| Licencia de Sitio | 20.000.000 COP al año | hasta 500 |

Formato de las claves emitidas por el portal:

```
VCP-E27C-02S4-9HKD-4B7Q
     │
     └── plan: P personal · E escuela · S sitio
```

---

## Accesibilidad y diseño

Tema claro y oscuro con conmutador que se guarda en el perfil, marcado semántico, enlace para
saltar al contenido, foco visible, contraste suficiente, tablas con desplazamiento propio,
menú lateral colapsable en móvil y hojas de impresión para informes y portafolios.
