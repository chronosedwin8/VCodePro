/* =============================================================================
   VCodePro — Precios vigentes en las páginas estáticas
   -----------------------------------------------------------------------------
   Los precios los fija la administración en el portal (Admin → Precios) y son
   los mismos que se cobran en Mercado Pago. Este script los pide al cargar la
   página y los pinta donde el HTML los marca con atributos data-plan-*.

   El HTML trae escritos los valores de fábrica: si esto no carga, la página se
   sigue viendo completa. Aunque esos valores quedaran viejos, nadie paga un
   precio distinto del vigente: comprar.php muestra y cobra el del servidor.

   Marcas que entiende, dentro de un elemento con data-plan="personal|escuela|sitio":
     data-plan-nombre     nombre del plan
     data-plan-precio     «$5.000.000»
     data-plan-periodo    «COP / año»
     data-plan-cupo       «100»
     data-plan-unitario   «$4.167» (por licencia al mes)
     data-plan-corto      «$5.000.000 / año»
   ============================================================================= */
(function () {
  "use strict";

  function pesos(valor) {
    return "$" + String(Math.round(valor)).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function poner(raiz, selector, texto) {
    raiz.querySelectorAll(selector).forEach(function (el) { el.textContent = texto; });
    if (raiz.matches && raiz.matches(selector)) { raiz.textContent = texto; }
  }

  function pintar(planes) {
    Object.keys(planes).forEach(function (clave) {
      var p = planes[clave];
      document.querySelectorAll('[data-plan="' + clave + '"]').forEach(function (raiz) {
        poner(raiz, "[data-plan-nombre]", p.nombre);
        poner(raiz, "[data-plan-precio]", pesos(p.precio));
        poner(raiz, "[data-plan-periodo]", "COP / " + p.periodo);
        poner(raiz, "[data-plan-cupo]", String(p.cupo));
        poner(raiz, "[data-plan-unitario]", pesos(p.por_licencia_mes));
        poner(raiz, "[data-plan-corto]", pesos(p.precio) + " / " + p.periodo);
      });
    });
    pintarDatosEstructurados(planes);
    pintarMetadatos(planes);
  }

  /** Ofertas del JSON-LD, para que los buscadores vean el precio vigente. */
  function pintarDatosEstructurados(planes) {
    var orden = ["personal", "escuela", "sitio"];
    document.querySelectorAll('script[type="application/ld+json"]').forEach(function (s) {
      var datos;
      try { datos = JSON.parse(s.textContent); } catch (e) { return; }
      if (!datos || !Array.isArray(datos.offers)) { return; }
      datos.offers.forEach(function (oferta, i) {
        var m = /#(personal|escuela|sitio)\b/.exec(oferta.url || "");
        var p = planes[m ? m[1] : orden[i]];
        if (!p) { return; }
        // «Licencia Personal» sigue siendo correcto mientras el plan se llame «Personal».
        if (String(oferta.name || "").indexOf(p.nombre) === -1) { oferta.name = p.nombre; }
        oferta.price = String(p.precio);
        if (oferta.eligibleQuantity && oferta.eligibleQuantity.maxValue) {
          oferta.eligibleQuantity.maxValue = p.cupo;
        }
      });
      s.textContent = JSON.stringify(datos);
    });
  }

  /** Descripciones para buscadores y redes, en las páginas que las marcan. */
  function pintarMetadatos(planes) {
    var per = planes.personal, esc = planes.escuela, sit = planes.sitio;
    if (!per || !esc || !sit) { return; }
    var al = function (p) { return p.periodo === "mes" ? "al mes" : "al año"; };
    var cuerpo = per.nombre + " " + pesos(per.precio) + " COP " + al(per) + ", " +
      esc.nombre + " " + pesos(esc.precio) + " COP " + al(esc) + " hasta " + esc.cupo + " licencias y " +
      sit.nombre + " " + pesos(sit.precio) + " COP " + al(sit) + " hasta " + sit.cupo + " licencias.";
    document.querySelectorAll("meta[data-plan-meta]").forEach(function (m) {
      m.setAttribute("content", m.getAttribute("data-plan-meta") === "largo"
        ? "Precios de VCodePro en pesos colombianos: " + cuerpo + " Calculadora incluida."
        : cuerpo);
    });
  }

  function publicar(planes) {
    window.VCodeProPlanes = planes;
    pintar(planes);
    var evento;
    try {
      evento = new CustomEvent("vcodepro:planes", { detail: planes });
    } catch (e) {
      evento = document.createEvent("CustomEvent");
      evento.initCustomEvent("vcodepro:planes", false, false, planes);
    }
    document.dispatchEvent(evento);
  }

  if (!window.fetch) { return; }
  fetch("portal/api/precios.php", { headers: { Accept: "application/json" } })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) { if (d && d.planes) { publicar(d.planes); } })
    .catch(function () { /* se quedan los valores escritos en el HTML */ });
})();
