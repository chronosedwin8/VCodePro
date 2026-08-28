/* =============================================================================
   VCodePro — Script principal (JavaScript puro, sin dependencias)
   Contiene: tema claro/oscuro, menú móvil, menú de descargas, detección del
   sistema operativo, animaciones de entrada, avisos emergentes y utilidades.
   ============================================================================= */
(function () {
  "use strict";

  /* ---------------------------------------------------------------------
     1. Catálogo de descargas
     Todas las descargas se sirven desde los servidores oficiales de
     Visual Studio Code (base de código abierto sobre la que se construye
     VCodePro). Los enlaces siempre entregan la versión estable más reciente.
     --------------------------------------------------------------------- */
  var BASE_INICIO = "https://update.code.visualstudio.com/latest/";
  var BASE_FIN = "/stable";

  var DESCARGAS = {
    windows: {
      nombre: "Windows",
      requisitos: "Windows 10, 11 · 64 bits o ARM64",
      principal: "win32-x64-user",
      opciones: [
        { id: "win32-x64-user",       etiqueta: "Instalador de usuario",   arq: "x64" },
        { id: "win32-arm64-user",     etiqueta: "Instalador de usuario",   arq: "ARM64" },
        { id: "win32-x64",            etiqueta: "Instalador de sistema",   arq: "x64" },
        { id: "win32-arm64",          etiqueta: "Instalador de sistema",   arq: "ARM64" },
        { id: "win32-x64-archive",    etiqueta: "Archivo .zip",            arq: "x64" },
        { id: "win32-arm64-archive",  etiqueta: "Archivo .zip",            arq: "ARM64" },
        { id: "cli-win32-x64",        etiqueta: "CLI de línea de comandos", arq: "x64" }
      ]
    },
    mac: {
      nombre: "macOS",
      requisitos: "macOS 11 o superior · Intel y Apple Silicon",
      principal: "darwin-universal",
      opciones: [
        { id: "darwin-universal",  etiqueta: "Paquete universal",         arq: "Universal" },
        { id: "darwin-arm64",      etiqueta: "Apple Silicon",             arq: "ARM64" },
        { id: "darwin",            etiqueta: "Procesador Intel",          arq: "x64" },
        { id: "cli-darwin-arm64",  etiqueta: "CLI de línea de comandos",  arq: "ARM64" },
        { id: "cli-darwin-x64",    etiqueta: "CLI de línea de comandos",  arq: "x64" }
      ]
    },
    linux: {
      nombre: "Linux",
      requisitos: "Debian, Ubuntu, Fedora, RHEL, SUSE · x64 y ARM",
      principal: "linux-deb-x64",
      opciones: [
        { id: "linux-deb-x64",    etiqueta: "Paquete .deb",              arq: "x64" },
        { id: "linux-deb-arm64",  etiqueta: "Paquete .deb",              arq: "ARM64" },
        { id: "linux-rpm-x64",    etiqueta: "Paquete .rpm",              arq: "x64" },
        { id: "linux-rpm-arm64",  etiqueta: "Paquete .rpm",              arq: "ARM64" },
        { id: "linux-x64",        etiqueta: "Archivo .tar.gz",           arq: "x64" },
        { id: "linux-arm64",      etiqueta: "Archivo .tar.gz",           arq: "ARM64" },
        { id: "cli-linux-x64",    etiqueta: "CLI de línea de comandos",  arq: "x64" }
      ]
    }
  };

  function urlDescarga(id) {
    return BASE_INICIO + id + BASE_FIN;
  }

  /* ---------------------------------------------------------------------
     2. Utilidades generales
     --------------------------------------------------------------------- */
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  var pesos = new Intl.NumberFormat("es-CO", {
    style: "currency", currency: "COP", minimumFractionDigits: 0, maximumFractionDigits: 0
  });

  function formatearCOP(valor) {
    return pesos.format(Math.round(valor));
  }

  var temporizadorAviso = null;
  function aviso(mensaje) {
    var caja = $("#toast");
    if (!caja) {
      caja = document.createElement("div");
      caja.id = "toast";
      caja.className = "toast";
      caja.setAttribute("role", "status");
      caja.setAttribute("aria-live", "polite");
      document.body.appendChild(caja);
    }
    caja.textContent = mensaje;
    caja.classList.add("show");
    window.clearTimeout(temporizadorAviso);
    temporizadorAviso = window.setTimeout(function () {
      caja.classList.remove("show");
    }, 3200);
  }

  /* ---------------------------------------------------------------------
     3. Tema claro / oscuro
     --------------------------------------------------------------------- */
  function temaGuardado() {
    try { return localStorage.getItem("vcodepro-tema"); } catch (e) { return null; }
  }

  function aplicarTema(tema) {
    document.documentElement.setAttribute("data-theme", tema);
    try { localStorage.setItem("vcodepro-tema", tema); } catch (e) { /* modo privado */ }
    var boton = $(".theme-toggle");
    if (boton) {
      boton.setAttribute("aria-label", tema === "dark" ? "Cambiar a tema claro" : "Cambiar a tema oscuro");
      boton.setAttribute("aria-pressed", tema === "dark" ? "true" : "false");
    }
  }

  function iniciarTema() {
    var guardado = temaGuardado();
    var oscuroSistema = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    aplicarTema(guardado || (oscuroSistema ? "dark" : "light"));

    var boton = $(".theme-toggle");
    if (boton) {
      boton.addEventListener("click", function () {
        var actual = document.documentElement.getAttribute("data-theme");
        aplicarTema(actual === "dark" ? "light" : "dark");
      });
    }
  }

  /* ---------------------------------------------------------------------
     4. Navegación móvil
     --------------------------------------------------------------------- */
  function iniciarNavegacion() {
    var boton = $(".nav-toggle");
    var lista = $("#nav-links");
    if (!boton || !lista) { return; }

    boton.addEventListener("click", function () {
      var abierto = boton.getAttribute("aria-expanded") === "true";
      boton.setAttribute("aria-expanded", String(!abierto));
      lista.setAttribute("data-open", String(!abierto));
    });

    lista.addEventListener("click", function (ev) {
      if (ev.target.tagName === "A" && window.innerWidth <= 900) {
        boton.setAttribute("aria-expanded", "false");
        lista.setAttribute("data-open", "false");
      }
    });

    document.addEventListener("keydown", function (ev) {
      if (ev.key === "Escape" && boton.getAttribute("aria-expanded") === "true") {
        boton.setAttribute("aria-expanded", "false");
        lista.setAttribute("data-open", "false");
        boton.focus();
      }
    });
  }

  /* ---------------------------------------------------------------------
     5. Detección del sistema operativo
     --------------------------------------------------------------------- */
  function detectarSO() {
    var plataforma = "";
    if (navigator.userAgentData && navigator.userAgentData.platform) {
      plataforma = navigator.userAgentData.platform;
    }
    var cadena = (plataforma + " " + navigator.userAgent + " " + (navigator.platform || "")).toLowerCase();

    if (cadena.indexOf("android") > -1) { return "linux"; }
    if (cadena.indexOf("mac") > -1 || cadena.indexOf("iphone") > -1 || cadena.indexOf("ipad") > -1) { return "mac"; }
    if (cadena.indexOf("win") > -1) { return "windows"; }
    if (cadena.indexOf("linux") > -1 || cadena.indexOf("x11") > -1) { return "linux"; }
    return "windows";
  }

  /* ---------------------------------------------------------------------
     6. Botón de descarga del encabezado + menú de plataformas
     --------------------------------------------------------------------- */
  function iniciarDescargaHero() {
    var grupo = $("#dl-group");
    if (!grupo) { return; }

    var principal = $("#dl-principal", grupo);
    var etiqueta = $("#dl-etiqueta", grupo);
    var caret = $("#dl-caret", grupo);
    var menu = $("#dl-menu", grupo);
    var so = detectarSO();
    var datos = DESCARGAS[so];

    if (principal && etiqueta) {
      principal.href = urlDescarga(datos.principal);
      etiqueta.textContent = "Descargar para " + datos.nombre;
      principal.setAttribute("data-so", so);
    }

    if (menu) {
      var html = "";
      Object.keys(DESCARGAS).forEach(function (clave) {
        var s = DESCARGAS[clave];
        s.opciones.slice(0, 3).forEach(function (op) {
          html += '<a href="' + urlDescarga(op.id) + '" rel="noopener nofollow">' +
                  s.nombre + " · " + op.etiqueta + "<span>" + op.arq + "</span></a>";
        });
      });
      html += '<a href="descargas.html"><strong>Ver todas las opciones</strong><span>&rarr;</span></a>';
      menu.innerHTML = html;
    }

    if (caret && menu) {
      caret.addEventListener("click", function (ev) {
        ev.stopPropagation();
        var abierto = menu.getAttribute("data-open") === "true";
        menu.setAttribute("data-open", String(!abierto));
        caret.setAttribute("aria-expanded", String(!abierto));
      });
      document.addEventListener("click", function (ev) {
        if (!grupo.contains(ev.target)) {
          menu.setAttribute("data-open", "false");
          caret.setAttribute("aria-expanded", "false");
        }
      });
      document.addEventListener("keydown", function (ev) {
        if (ev.key === "Escape") {
          menu.setAttribute("data-open", "false");
          caret.setAttribute("aria-expanded", "false");
        }
      });
    }
  }

  /* ---------------------------------------------------------------------
     7. Página de descargas: rellena tarjetas y resalta el SO detectado
     --------------------------------------------------------------------- */
  function iniciarPaginaDescargas() {
    var contenedor = $("#dl-cards");
    if (!contenedor) { return; }

    var so = detectarSO();
    var detectado = $("#so-detectado");
    if (detectado) {
      detectado.textContent = DESCARGAS[so].nombre;
    }

    $$("[data-plataforma]", contenedor).forEach(function (tarjeta) {
      var clave = tarjeta.getAttribute("data-plataforma");
      var datos = DESCARGAS[clave];
      if (!datos) { return; }

      var botonPrincipal = $(".btn", tarjeta);
      if (botonPrincipal) {
        botonPrincipal.href = urlDescarga(datos.principal);
      }

      var lista = $(".dl-list", tarjeta);
      if (lista) {
        lista.innerHTML = datos.opciones.map(function (op) {
          return "<li><a href=\"" + urlDescarga(op.id) + "\" rel=\"noopener nofollow\">" +
                 op.etiqueta + '<span class="arch">' + op.arq + "</span></a></li>";
        }).join("");
      }

      if (clave === so) {
        tarjeta.classList.add("featured");
        var marca = $(".tag", tarjeta);
        if (marca) { marca.hidden = false; }
      }
    });
  }

  /* ---------------------------------------------------------------------
     8. Registro de descargas (solo local, sin envío a terceros)
     --------------------------------------------------------------------- */
  function iniciarRegistroDescargas() {
    document.addEventListener("click", function (ev) {
      var enlace = ev.target.closest ? ev.target.closest('a[href*="update.code.visualstudio.com"]') : null;
      if (!enlace) { return; }
      aviso("Iniciando la descarga desde los servidores oficiales…");
    });
  }

  /* ---------------------------------------------------------------------
     9. Animaciones de entrada
     --------------------------------------------------------------------- */
  function iniciarAnimaciones() {
    var elementos = $$(".reveal");
    if (!elementos.length) { return; }

    if (!("IntersectionObserver" in window)) {
      elementos.forEach(function (el) { el.classList.add("in"); });
      return;
    }

    var observador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add("in");
          observador.unobserve(entrada.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

    elementos.forEach(function (el, i) {
      el.style.transitionDelay = Math.min(i % 4, 3) * 70 + "ms";
      observador.observe(el);
    });
  }

  /* ---------------------------------------------------------------------
     10. Detalles finales de cada página
     --------------------------------------------------------------------- */
  function iniciarVarios() {
    var anio = $("#anio");
    if (anio) { anio.textContent = String(new Date().getFullYear()); }

    // Marca el enlace de navegación de la página actual.
    var archivo = window.location.pathname.split("/").pop() || "index.html";
    $$("#nav-links a").forEach(function (a) {
      var destino = a.getAttribute("href");
      if (destino === archivo) { a.setAttribute("aria-current", "page"); }
    });

    // Botones que copian texto al portapapeles.
    $$("[data-copiar]").forEach(function (boton) {
      boton.addEventListener("click", function () {
        var origen = document.getElementById(boton.getAttribute("data-copiar"));
        if (!origen) { return; }
        var texto = origen.value !== undefined ? origen.value : origen.textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(texto.trim()).then(function () {
            aviso("Copiado al portapapeles.");
          }, function () {
            aviso("No fue posible copiar. Selecciona el texto manualmente.");
          });
        } else {
          aviso("Tu navegador no permite copiar automáticamente.");
        }
      });
    });
  }

  /* ---------------------------------------------------------------------
     11. API pública para el resto de scripts del sitio
     --------------------------------------------------------------------- */
  window.VCodePro = {
    descargas: DESCARGAS,
    urlDescarga: urlDescarga,
    formatearCOP: formatearCOP,
    aviso: aviso,
    detectarSO: detectarSO,
    $: $,
    $$: $$
  };

  /* ---------------------------------------------------------------------
     12. Arranque
     --------------------------------------------------------------------- */
  function iniciar() {
    iniciarTema();
    iniciarNavegacion();
    iniciarDescargaHero();
    iniciarPaginaDescargas();
    iniciarRegistroDescargas();
    iniciarAnimaciones();
    iniciarVarios();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
