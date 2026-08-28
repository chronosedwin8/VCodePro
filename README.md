# VCodePro — sitio web

Sitio estático del editor de código **VCodePro**: enseñanza de la programación desde los 12 años,
con inteligencia artificial incorporada para la creación de agentes y enfoque en las electivas de
tecnología del Bachillerato Internacional.

Construido únicamente con **HTML5, CSS3 y JavaScript puro**. Sin dependencias, sin frameworks y sin
paso de compilación: basta con abrir `index.html` o publicar la carpeta en cualquier servidor.

## Estructura

```
vcodepro/
├── index.html              Portada: IA y agentes, características, planes
├── caracteristicas.html    Detalle del producto (#agentes = estudio de agentes)
├── educacion.html          Propuesta pedagógica IB, plan por niveles, formación docente
├── descargas.html          Descargas por plataforma y despliegue en salas de cómputo
├── precios.html            Planes, calculadora de licencias y preguntas frecuentes
├── licencias.html          Portal: activar clave, panel y reparto de puestos
├── documentacion.html      Guías de uso, atajos y solución de problemas
├── contacto.html           Formulario de contacto con validación
├── privacidad.html         Privacidad y términos de uso
├── 404.html                Página de error
├── manifest.webmanifest    Aplicación web instalable
├── robots.txt · sitemap.xml
└── assets/
    ├── css/styles.css      Sistema de diseño completo (tema claro y oscuro)
    ├── js/main.js          Tema, menú, catálogo de descargas, detección del sistema
    ├── js/precios.js       Calculadora de licenciamiento y cotización descargable
    ├── js/licencias.js     Generación, validación y administración de licencias
    ├── js/contacto.js      Validación del formulario
    └── img/                Logotipo, iconos e imagen para redes sociales
```

## Descargas

Los botones de descarga apuntan a los servidores oficiales de Visual Studio Code:

```
https://update.code.visualstudio.com/latest/<plataforma>/stable
```

Se incluyen 19 combinaciones de sistema y arquitectura (Windows, macOS y Linux, más la CLI).
El catálogo está en un solo lugar, `assets/js/main.js`, y la página `descargas.html` trae los mismos
enlaces escritos en el HTML para que todo funcione aunque el navegador tenga JavaScript desactivado.

## Licenciamiento

| Plan               | Precio                | Cupo              |
|--------------------|-----------------------|-------------------|
| Personal           | 150.000 COP al mes    | 1 licencia        |
| Escuela            | 5.000.000 COP al año  | hasta 100         |
| Licencia de Sitio  | 20.000.000 COP al año | hasta 500         |

Valores finales, sin impuestos añadidos: VCodePro tiene sede en Alemania y la licencia se vende como
servicio digital internacional. La calculadora de `precios.html` elige el plan más económico según el
número de licencias, muestra el costo por licencia y genera una cotización descargable.

### Formato de las claves

```
VCP-E27C-02S4-9HKD-4B7Q
     │     │    │    └── dígito de control (verificación sin conexión)
     │     │    └─────── serie única
     │     └──────────── cupo de licencias codificado
     └────────────────── plan (P, E, S) y fecha de vencimiento
```

El portal de licencias genera claves de demostración, valida el dígito de control, detecta licencias
vencidas y administra el reparto de puestos. Todo ocurre en el navegador: los datos se guardan en
`localStorage` y no se envían a ningún servidor.

## SEO y accesibilidad

- Título, descripción, palabras clave y URL canónica propios en cada página.
- Open Graph y Twitter Card con imagen de 1200 × 630 px.
- Datos estructurados JSON-LD: `Organization`, `WebSite`, `SoftwareApplication`, `Product` con las
  tres ofertas, `FAQPage`, `ContactPage` y `BreadcrumbList`.
- `sitemap.xml`, `robots.txt` y manifiesto de aplicación web.
- Marcado semántico, enlace para saltar al contenido, foco visible, textos alternativos, contraste
  suficiente y respeto por `prefers-reduced-motion`.
- Sin desbordamiento horizontal en pantallas pequeñas; tema claro y oscuro con conmutador manual.

## Publicación

No requiere compilación. Copia la carpeta a cualquier alojamiento estático (Apache, Nginx, GitHub
Pages, Netlify). Si cambias el dominio, actualiza la constante `BASE` en las URL canónicas de cada
página, `sitemap.xml` y `robots.txt`.
