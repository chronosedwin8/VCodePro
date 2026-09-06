/* ==========================================================================
   Editor enriquecido del portal
   --------------------------------------------------------------------------
   Mejora progresiva sobre <textarea data-rico>: si el JavaScript no carga,
   el estudiante sigue viendo un área de texto normal y su trabajo se guarda
   igual. El textarea nunca se elimina del DOM; se oculta y sigue siendo el
   campo real del formulario, así que no hay que tocar ningún <form>.

   Dos cuidados importantes:
   - Cada cambio se refleja en el textarea y se dispara en él un evento
     `input` (y `blur`), porque de ahí cuelga el autoguardado de fases que
     vive en portal.js.
   - Lo que se pega se limpia aquí y se vuelve a limpiar en el servidor
     (includes/richtext.php). El saneado del navegador es comodidad; el que
     protege al docente que califica es el del servidor.
   ========================================================================== */
(function () {
  "use strict";

  var PERMITIDAS = ["P", "BR", "STRONG", "EM", "U", "S", "UL", "OL", "LI",
                    "H3", "H4", "BLOCKQUOTE", "CODE", "PRE", "A"];
  var DESCARTAR  = ["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "NOSCRIPT",
                    "TEMPLATE", "LINK", "META", "FORM", "SVG", "MATH"];

  var HERRAMIENTAS = [
    { cmd: "bold",          etiqueta: "Negrita",            tecla: "Ctrl+B", icono: "N", clase: "es-negrita" },
    { cmd: "italic",        etiqueta: "Cursiva",            tecla: "Ctrl+I", icono: "C", clase: "es-cursiva" },
    { cmd: "underline",     etiqueta: "Subrayado",          tecla: "Ctrl+U", icono: "S", clase: "es-subrayado" },
    { sep: true },
    { cmd: "formatBlock", valor: "h3", etiqueta: "Título",   icono: "T" },
    { cmd: "insertUnorderedList", etiqueta: "Lista con viñetas", icono: "•—" },
    { cmd: "insertOrderedList",   etiqueta: "Lista numerada",    icono: "1—" },
    { cmd: "formatBlock", valor: "blockquote", etiqueta: "Cita", icono: "❝" },
    { cmd: "formatBlock", valor: "pre", etiqueta: "Bloque de código", icono: "</>", clase: "es-codigo" },
    { sep: true },
    { cmd: "enlace",      etiqueta: "Insertar enlace",  icono: "🔗" },
    { cmd: "removeFormat", etiqueta: "Quitar formato",  icono: "✕" }
  ];

  // Misma prueba que en el servidor: un «if (a < b)» guardado como texto plano
  // no debe confundirse con marcas HTML.
  var PATRON_ETIQUETA =
    /<\/?(p|br|strong|em|u|s|ul|ol|li|h3|h4|blockquote|code|pre|a)\b[^>]*>/i;

  /* ------------------------------------------------------------ saneado -- */

  function enlaceSeguro(url) {
    var limpia = String(url || "").replace(/[\s\u0000-\u001F]+/g, "").toLowerCase();
    if (!limpia) return false;
    var malos = ["javascript:", "data:", "vbscript:", "file:"];
    for (var i = 0; i < malos.length; i++) {
      if (limpia.indexOf(malos[i]) === 0) return false;
    }
    return /^(https?:\/\/|mailto:|\/)/.test(limpia);
  }

  /** Deja el fragmento en la lista blanca, con la misma política del servidor. */
  function sanear(nodo) {
    Array.prototype.slice.call(nodo.childNodes).forEach(function (hijo) {
      if (hijo.nodeType === 3) return;                 // texto: se conserva
      if (hijo.nodeType !== 1) { hijo.remove(); return; } // comentarios y demás

      var etiqueta = hijo.tagName.toUpperCase();

      if (DESCARTAR.indexOf(etiqueta) !== -1) { hijo.remove(); return; }

      if (PERMITIDAS.indexOf(etiqueta) === -1) {
        sanear(hijo);
        desenvolver(hijo);
        return;
      }

      Array.prototype.slice.call(hijo.attributes).forEach(function (attr) {
        var nombre = attr.name.toLowerCase();
        var esHref = etiqueta === "A" && nombre === "href";
        if (!esHref || !enlaceSeguro(attr.value)) hijo.removeAttribute(attr.name);
      });

      if (etiqueta === "A") {
        if (hijo.hasAttribute("href")) {
          hijo.setAttribute("rel", "noopener nofollow");
          hijo.setAttribute("target", "_blank");
        } else {
          sanear(hijo);
          desenvolver(hijo);
          return;
        }
      }

      sanear(hijo);
    });
    return nodo;
  }

  /** Quita la etiqueta pero deja dentro su contenido. */
  function desenvolver(el) {
    var padre = el.parentNode;
    if (!padre) return;
    while (el.firstChild) padre.insertBefore(el.firstChild, el);
    padre.removeChild(el);
  }

  function saneadoDe(html) {
    var caja = document.createElement("div");
    caja.innerHTML = String(html || "");
    sanear(caja);
    return caja.innerHTML;
  }

  /** El contenido antiguo es texto plano: se convierte a párrafos. */
  function desdeTextoPlano(texto) {
    return String(texto || "")
      .split(/\n{2,}/)
      .map(function (bloque) { return bloque.trim(); })
      .filter(Boolean)
      .map(function (bloque) {
        var div = document.createElement("div");
        div.textContent = bloque;
        return "<p>" + div.innerHTML.replace(/\n/g, "<br>") + "</p>";
      })
      .join("");
  }

  function contenidoInicial(valor) {
    var v = String(valor || "").trim();
    if (!v) return "";
    return PATRON_ETIQUETA.test(v) ? saneadoDe(v) : desdeTextoPlano(v);
  }

  function contarPalabras(area) {
    var texto = (area.textContent || "").trim();
    return texto ? texto.split(/\s+/).length : 0;
  }

  /* ------------------------------------------------------------- montaje -- */

  function montar(ta) {
    if (ta.dataset.ricoListo) return;
    ta.dataset.ricoListo = "1";

    var soloLectura = ta.disabled || ta.readOnly;

    var caja = document.createElement("div");
    caja.className = "rico" + (soloLectura ? " rico-bloqueado" : "");

    var area = document.createElement("div");
    area.className = "rico-area";
    area.innerHTML = contenidoInicial(ta.value);
    if (ta.style.minHeight) area.style.minHeight = ta.style.minHeight;

    if (soloLectura) {
      // Sin barra ni edición: el trabajo ya calificado se consulta, no se toca.
      area.setAttribute("aria-readonly", "true");
      caja.appendChild(area);
    } else {
      area.contentEditable = "true";
      area.setAttribute("role", "textbox");
      area.setAttribute("aria-multiline", "true");
      area.dataset.vacio = ta.placeholder || "Escribe aquí tu respuesta.";
      caja.appendChild(barraDe(area));
      caja.appendChild(area);
    }

    var pie = document.createElement("div");
    pie.className = "rico-pie";
    var cuenta = document.createElement("span");
    cuenta.className = "rico-cuenta";
    pie.appendChild(cuenta);
    caja.appendChild(pie);

    ta.parentNode.insertBefore(caja, ta);
    caja.appendChild(ta);
    ta.hidden = true;
    ta.setAttribute("aria-hidden", "true");
    ta.tabIndex = -1;

    // Un campo obligatorio oculto bloquea el envío del formulario sin decir por
    // qué («not focusable»), así que la obligatoriedad se valida aquí.
    if (ta.required) {
      ta.required = false;
      caja.dataset.obligatorio = "1";
      area.setAttribute("aria-required", "true");
      vigilarFormulario(ta.form);
    }

    function refrescarCuenta() {
      var n = contarPalabras(area);
      cuenta.textContent = n === 1 ? "1 palabra" : n + " palabras";
    }

    /** Vuelca el editor en el textarea y avisa a quien lo esté escuchando. */
    function sincronizar(evento) {
      var html = area.innerHTML;
      // Al borrarlo todo, el navegador deja un <br> suelto: eso es vacío.
      if (!area.textContent.trim()) html = "";
      ta.value = html;
      refrescarCuenta();
      area.classList.toggle("es-vacio", html === "");
      if (evento) ta.dispatchEvent(new Event(evento, { bubbles: false }));
    }

    if (!soloLectura) {
      area.addEventListener("input", function () { sincronizar("input"); });
      area.addEventListener("blur", function () { sincronizar("blur"); });

      // Se pega siempre como texto plano: nada de estilos ajenos ni HTML raro.
      area.addEventListener("paste", function (e) {
        e.preventDefault();
        var texto = (e.clipboardData || window.clipboardData).getData("text/plain");
        document.execCommand("insertText", false, texto);
      });

      area.addEventListener("drop", function (e) { e.preventDefault(); });

      area.addEventListener("keydown", function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
        var mapa = { b: "bold", i: "italic", u: "underline" };
        var cmd = mapa[e.key.toLowerCase()];
        if (!cmd) return;
        e.preventDefault();
        ejecutar(cmd, null, area);
        sincronizar("input");
      });

      // Enlazar el rótulo del textarea con el área editable.
      var rotulo = ta.id ? document.querySelector('label[for="' + ta.id + '"]') : null;
      if (rotulo) {
        rotulo.addEventListener("click", function (e) { e.preventDefault(); area.focus(); });
        if (!area.getAttribute("aria-label")) {
          area.setAttribute("aria-label", rotulo.textContent.trim());
        }
      }
    }

    sincronizar(null);
    area.classList.toggle("es-vacio", area.innerHTML === "");
  }

  /**
   * Impide enviar un formulario con un editor obligatorio vacío y lleva el
   * foco al que falta, que es lo que haría el navegador con un textarea.
   */
  function vigilarFormulario(form) {
    if (!form || form.dataset.ricoVigilado) return;
    form.dataset.ricoVigilado = "1";
    form.addEventListener("submit", function (e) {
      var faltante = null;
      form.querySelectorAll('.rico[data-obligatorio]').forEach(function (caja) {
        var area = caja.querySelector(".rico-area");
        if (!faltante && area && !area.textContent.trim()) faltante = caja;
        caja.classList.toggle("es-falta", area && !area.textContent.trim());
      });
      if (faltante) {
        e.preventDefault();
        var area = faltante.querySelector(".rico-area");
        faltante.scrollIntoView({ block: "center", behavior: "smooth" });
        if (area) area.focus();
      }
    });
  }

  /* --------------------------------------------------------------- barra -- */

  function barraDe(area) {
    var barra = document.createElement("div");
    barra.className = "rico-barra";
    barra.setAttribute("role", "toolbar");
    barra.setAttribute("aria-label", "Formato del texto");

    HERRAMIENTAS.forEach(function (h) {
      if (h.sep) {
        var sep = document.createElement("span");
        sep.className = "rico-sep";
        sep.setAttribute("aria-hidden", "true");
        barra.appendChild(sep);
        return;
      }
      var b = document.createElement("button");
      b.type = "button";                       // si no, envía el formulario
      b.className = "rico-btn" + (h.clase ? " " + h.clase : "");
      b.textContent = h.icono;
      b.title = h.etiqueta + (h.tecla ? " (" + h.tecla + ")" : "");
      b.setAttribute("aria-label", h.etiqueta);
      b.dataset.cmd = h.cmd;
      if (h.valor) b.dataset.valor = h.valor;
      // mousedown, no click: así no se pierde la selección del texto.
      b.addEventListener("mousedown", function (e) {
        e.preventDefault();
        area.focus();
        ejecutar(h.cmd, h.valor, area);
        // El propio editor escucha "input" y desde ahí vuelca al textarea.
        area.dispatchEvent(new Event("input", { bubbles: true }));
        estadoBotones(barra);
      });
      barra.appendChild(b);
    });

    document.addEventListener("selectionchange", function () {
      if (document.activeElement === area) estadoBotones(barra);
    });

    return barra;
  }

  function ejecutar(cmd, valor, area) {
    if (cmd === "enlace") { insertarEnlace(area); return; }
    if (cmd === "formatBlock") {
      // Si ya está aplicado, el botón lo quita: se comporta como interruptor.
      var actual = bloqueActual();
      document.execCommand("formatBlock", false, actual === valor ? "p" : valor);
      return;
    }
    document.execCommand(cmd, false, null);
  }

  function bloqueActual() {
    try {
      return String(document.queryCommandValue("formatBlock") || "").toLowerCase();
    } catch (e) { return ""; }
  }

  function insertarEnlace(area) {
    var sel = window.getSelection();
    var texto = sel && !sel.isCollapsed ? sel.toString() : "";
    var url = window.prompt("Dirección del enlace (empieza por https://)", "https://");
    if (url === null) return;
    url = url.trim();
    if (!url || url === "https://") return;
    if (!enlaceSeguro(url)) {
      window.alert("Ese enlace no es válido. Usa una dirección https:// o un correo mailto:");
      return;
    }
    if (texto) {
      document.execCommand("createLink", false, url);
    } else {
      var a = document.createElement("a");
      a.href = url;
      a.textContent = url;
      a.rel = "noopener nofollow";
      a.target = "_blank";
      insertarNodo(a, area);
    }
  }

  function insertarNodo(nodo, area) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) { area.appendChild(nodo); return; }
    var rango = sel.getRangeAt(0);
    rango.deleteContents();
    rango.insertNode(nodo);
    rango.setStartAfter(nodo);
    rango.collapse(true);
    sel.removeAllRanges();
    sel.addRange(rango);
  }

  function estadoBotones(barra) {
    var bloque = bloqueActual();
    barra.querySelectorAll(".rico-btn").forEach(function (b) {
      var activo = false;
      try {
        if (b.dataset.cmd === "formatBlock") activo = bloque === b.dataset.valor;
        else if (b.dataset.cmd !== "enlace" && b.dataset.cmd !== "removeFormat") {
          activo = document.queryCommandState(b.dataset.cmd);
        }
      } catch (e) { activo = false; }
      b.classList.toggle("es-activo", activo);
      b.setAttribute("aria-pressed", activo ? "true" : "false");
    });
  }

  /* -------------------------------------------------------------- arranque */

  function iniciar() {
    if (!document.queryCommandSupported || !document.queryCommandSupported("bold")) return;
    try { document.execCommand("defaultParagraphSeparator", false, "p"); } catch (e) {}
    document.querySelectorAll("textarea[data-rico]").forEach(montar);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
