/* =============================================================================
   VCodePro — Formulario de contacto (JavaScript puro)
   Valida los campos en el navegador, registra el mensaje en el portal
   (portal/api/contacto.php) y deja listo el correo como alternativa.
   ============================================================================= */
(function () {
  "use strict";

  var API = window.VCodePro || {};
  var $ = API.$ || function (s, c) { return (c || document).querySelector(s); };
  var $$ = API.$$ || function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var formulario = $("#form-contacto");
  if (!formulario) { return; }

  var CORREO = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;

  /* ---------- Motivo preseleccionado desde la dirección web ---------- */
  var MOTIVOS = {
    personal: "Licencia Personal",
    escuela: "Licencia Escuela",
    sitio: "Licencia de Sitio",
    licencia: "Clave institucional",
    cotizacion: "Cotización formal",
    formacion: "Formación docente",
    educacion: "Propuesta pedagógica",
    soporte: "Soporte técnico"
  };

  var parametros = new URLSearchParams(window.location.search);
  var motivo = parametros.get("motivo");
  var selectorMotivo = $("#c-motivo");
  if (motivo && MOTIVOS[motivo]) {
    $$("option", selectorMotivo).forEach(function (op) {
      if (op.value === MOTIVOS[motivo]) { op.selected = true; }
    });
  }

  /* ---------- Validación ---------- */
  function contenedor(campo) {
    return campo.closest(".field");
  }

  function marcar(campo, valido) {
    var caja = contenedor(campo);
    if (!caja) { return; }
    caja.classList.toggle("invalid", !valido);
    campo.setAttribute("aria-invalid", valido ? "false" : "true");
  }

  function validarCampo(campo) {
    var valor = (campo.value || "").trim();
    var valido = true;

    if (campo.hasAttribute("required") && !valor) { valido = false; }
    if (valido && campo.type === "email" && valor) { valido = CORREO.test(valor); }
    if (valido && campo.id === "c-licencias" && valor) {
      var n = parseInt(valor, 10);
      valido = !isNaN(n) && n >= 1 && n <= 5000;
    }
    if (valido && campo.type === "checkbox" && campo.hasAttribute("required")) {
      valido = campo.checked;
    }
    marcar(campo, valido);
    return valido;
  }

  var campos = $$("input, select, textarea", formulario).filter(function (el) {
    return el.type !== "submit" && el.type !== "button";
  });

  campos.forEach(function (campo) {
    campo.addEventListener("blur", function () { validarCampo(campo); });
    campo.addEventListener("input", function () {
      if (contenedor(campo) && contenedor(campo).classList.contains("invalid")) { validarCampo(campo); }
    });
  });

  /* ---------- Envío ---------- */
  formulario.addEventListener("submit", function (ev) {
    ev.preventDefault();

    var valido = true;
    campos.forEach(function (campo) {
      if (!validarCampo(campo)) { valido = false; }
    });

    if (!valido) {
      var primerError = $(".field.invalid input, .field.invalid select, .field.invalid textarea", formulario);
      if (primerError) { primerError.focus(); }
      if (API.aviso) { API.aviso("Revisa los campos marcados en rojo."); }
      return;
    }

    var datos = {
      nombre: $("#c-nombre").value.trim(),
      correo: $("#c-correo").value.trim(),
      institucion: $("#c-institucion").value.trim(),
      cargo: $("#c-cargo").value.trim(),
      telefono: $("#c-telefono").value.trim(),
      motivo: $("#c-motivo").value,
      licencias: $("#c-licencias").value.trim(),
      mensaje: $("#c-mensaje").value.trim()
    };

    var cuerpo = [
      "Nombre: " + datos.nombre,
      "Correo: " + datos.correo,
      "Institución: " + datos.institucion,
      "Cargo: " + (datos.cargo || "No indicado"),
      "Teléfono: " + (datos.telefono || "No indicado"),
      "Motivo: " + datos.motivo,
      "Licencias estimadas: " + (datos.licencias || "Por definir"),
      "",
      "Mensaje:",
      datos.mensaje
    ].join("\n");

    var enlace = "mailto:licencias@vcodepro.de" +
      "?subject=" + encodeURIComponent("[" + datos.motivo + "] " + datos.institucion) +
      "&body=" + encodeURIComponent(cuerpo);

    var confirmacion = $("#c-exito");
    confirmacion.hidden = false;
    $("#c-resumen").textContent = datos.motivo + " · " + datos.institucion +
      (datos.licencias ? " · " + datos.licencias + " licencias" : "");
    $("#c-mailto").href = enlace;
    $("#c-copia").value = cuerpo;
    confirmacion.scrollIntoView({ behavior: "smooth", block: "center" });
    formulario.hidden = true;

    /* Registro en el portal. Si el servidor no responde, el mensaje sigue
       disponible para enviarlo por correo desde el botón de la confirmación. */
    var cuerpoEnvio = new FormData();
    Object.keys(datos).forEach(function (k) { cuerpoEnvio.append(k, datos[k]); });
    cuerpoEnvio.append("institucion", datos.institucion);

    var estado = $("#c-estado-envio");
    if (estado) { estado.textContent = "Enviando…"; }

    fetch("portal/api/contacto.php", { method: "POST", body: cuerpoEnvio })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (estado) {
          estado.textContent = d && d.ok
            ? "Mensaje registrado. Te responderemos al correo indicado."
            : "No pudimos registrarlo en el servidor; envíalo por correo con el botón de abajo.";
        }
      })
      .catch(function () {
        if (estado) { estado.textContent = "Sin conexión con el servidor; envíalo por correo con el botón de abajo."; }
      });
  });

  /* ---------- Volver al formulario ---------- */
  var volver = $("#c-volver");
  if (volver) {
    volver.addEventListener("click", function () {
      $("#c-exito").hidden = true;
      formulario.hidden = false;
      formulario.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }
})();
