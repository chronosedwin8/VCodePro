/* =============================================================================
   VCodePro — Calculadora de licenciamiento (JavaScript puro)
   Calcula el plan recomendado y el valor total en pesos colombianos, y genera
   una cotización descargable. Los precios son finales: VCodePro factura desde
   Alemania como venta digital internacional, sin impuestos añadidos.
   ============================================================================= */
(function () {
  "use strict";

  var API = window.VCodePro || {};
  var $ = API.$ || function (s, c) { return (c || document).querySelector(s); };
  var $$ = API.$$ || function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var COP = API.formatearCOP || function (v) { return "$" + Math.round(v); };

  var formulario = $("#calc-form");
  if (!formulario) { return; }

  /* ---------------------------------------------------------------------
     1. Tarifas oficiales (pesos colombianos, valores finales)
     --------------------------------------------------------------------- */
  var PLANES = {
    personal: {
      id: "personal",
      nombre: "Personal",
      mensualPorLicencia: 150000,
      mesesAnual: 10,          // el pago anual anticipado cubre 12 meses y se cobran 10
      maxLicencias: 10,
      descripcion: "Por usuario, ideal para docentes y estudiantes independientes."
    },
    escuela: {
      id: "escuela",
      nombre: "Escuela",
      anual: 5000000,
      maxLicencias: 100,
      descripcion: "Un solo pago anual para toda la institución, hasta 100 licencias."
    },
    sitio: {
      id: "sitio",
      nombre: "Licencia de Sitio",
      anual: 20000000,
      maxLicencias: 500,
      descripcion: "Cobertura de campus completo, hasta 500 licencias."
    }
  };

  /* ---------------------------------------------------------------------
     2. Cálculo de costos
     --------------------------------------------------------------------- */
  function costoPersonal(licencias, periodo) {
    var mensual = PLANES.personal.mensualPorLicencia * licencias;
    if (periodo === "anual") {
      return mensual * PLANES.personal.mesesAnual;
    }
    return mensual;
  }

  function costoEscuela(periodo) {
    return periodo === "anual" ? PLANES.escuela.anual : PLANES.escuela.anual / 12;
  }

  function costoSitio(periodo) {
    return periodo === "anual" ? PLANES.sitio.anual : PLANES.sitio.anual / 12;
  }

  function costoDe(plan, licencias, periodo) {
    if (plan === "personal") { return costoPersonal(licencias, periodo); }
    if (plan === "escuela") { return costoEscuela(periodo); }
    if (plan === "sitio") { return costoSitio(periodo); }
    return 0;
  }

  function planesElegibles(licencias) {
    var lista = [];
    if (licencias <= PLANES.personal.maxLicencias) { lista.push("personal"); }
    if (licencias <= PLANES.escuela.maxLicencias) { lista.push("escuela"); }
    if (licencias <= PLANES.sitio.maxLicencias) { lista.push("sitio"); }
    return lista;
  }

  function recomendar(licencias, periodo) {
    var elegibles = planesElegibles(licencias);
    if (!elegibles.length) { return null; }
    return elegibles.reduce(function (mejor, actual) {
      return costoDe(actual, licencias, periodo) < costoDe(mejor, licencias, periodo) ? actual : mejor;
    }, elegibles[0]);
  }

  /* ---------------------------------------------------------------------
     3. Lectura del formulario
     --------------------------------------------------------------------- */
  var entradaLicencias = $("#cal-licencias");
  var rangoLicencias = $("#cal-rango");
  var selectorPlan = $("#cal-plan");
  var botonesPeriodo = $$("#cal-periodo button");
  var periodoActual = "anual";
  var ultimoCalculo = null;

  function leerLicencias() {
    var valor = parseInt(entradaLicencias.value, 10);
    if (isNaN(valor) || valor < 1) { valor = 1; }
    if (valor > 2000) { valor = 2000; }
    return valor;
  }

  /* ---------------------------------------------------------------------
     4. Presentación de resultados
     --------------------------------------------------------------------- */
  function textoPeriodo(periodo) {
    return periodo === "anual" ? "año" : "mes";
  }

  function actualizar() {
    var licencias = leerLicencias();
    entradaLicencias.value = licencias;
    if (rangoLicencias) { rangoLicencias.value = Math.min(licencias, parseInt(rangoLicencias.max, 10)); }

    var elegibles = planesElegibles(licencias);
    var seleccion = selectorPlan.value;
    var recomendado = recomendar(licencias, periodoActual);
    var plan = seleccion === "auto" ? recomendado : seleccion;

    // Marca en el desplegable los planes que no cubren la cantidad pedida.
    $$("option", selectorPlan).forEach(function (op) {
      if (op.value === "auto") { return; }
      var permitido = elegibles.indexOf(op.value) > -1;
      op.disabled = !permitido;
      op.textContent = op.getAttribute("data-nombre") + (permitido ? "" : " (supera el máximo)");
    });

    var salida = $("#calc-salida");
    var aviso = $("#calc-aviso");

    // Más de 500 licencias: cotización a la medida.
    if (!plan) {
      salida.hidden = true;
      aviso.hidden = false;
      aviso.innerHTML = "<strong>Más de 500 licencias.</strong> Este volumen se atiende con un " +
        "acuerdo marco a la medida (varias sedes, red de colegios o secretaría de educación). " +
        '<a href="contacto.html?motivo=sitio">Escríbenos y preparamos la propuesta</a>.';
      ultimoCalculo = null;
      return;
    }

    if (seleccion !== "auto" && elegibles.indexOf(seleccion) === -1) {
      plan = recomendado;
      selectorPlan.value = "auto";
    }

    aviso.hidden = true;
    salida.hidden = false;

    var datos = PLANES[plan];
    var total = costoDe(plan, licencias, periodoActual);
    var mesesCubiertos = periodoActual === "anual" ? 12 : 1;
    var unitario = total / licencias / mesesCubiertos;

    $("#out-plan").textContent = datos.nombre;
    $("#out-plan-desc").textContent = datos.descripcion;
    $("#out-recomendado").hidden = (plan !== recomendado);

    var detalle;
    if (plan === "personal") {
      detalle = licencias + (licencias === 1 ? " licencia" : " licencias") + " x " +
        COP(PLANES.personal.mensualPorLicencia) + " al mes" +
        (periodoActual === "anual" ? " x " + PLANES.personal.mesesAnual + " meses facturados (12 meses de uso)" : "");
    } else {
      detalle = "Tarifa única para hasta " + datos.maxLicencias + " licencias" +
        (periodoActual === "anual" ? " durante 12 meses" : ", prorrateada al mes");
    }
    $("#out-detalle").textContent = detalle;

    $("#out-total").textContent = COP(total);
    $("#out-periodo").textContent = "por " + textoPeriodo(periodoActual);
    $("#out-unitario").textContent = COP(unitario);

    // Cupo aprovechado del plan.
    var cupoMax = plan === "personal" ? licencias : datos.maxLicencias;
    var porcentaje = Math.min(100, Math.round((licencias / cupoMax) * 100));
    $("#out-cupo-barra").style.width = porcentaje + "%";
    $("#out-cupo-texto").textContent = plan === "personal"
      ? licencias + (licencias === 1 ? " licencia individual" : " licencias individuales")
      : licencias + " de " + datos.maxLicencias + " licencias usadas (" + porcentaje + "% del cupo)";

    // Comparación contra el plan Personal.
    var filaAhorro = $("#out-ahorro-fila");
    if (plan !== "personal") {
      var equivalente = costoPersonal(licencias, periodoActual);
      var ahorro = equivalente - total;
      if (ahorro > 0) {
        filaAhorro.hidden = false;
        $("#out-ahorro").textContent = COP(ahorro) + " frente a " + licencias + " licencias Personal";
      } else {
        filaAhorro.hidden = true;
      }
    } else {
      filaAhorro.hidden = true;
    }

    // Lo calculado se compra tal cual: Personal lleva cantidad y periodo;
    // Escuela y Sitio son tarifa única y se pagan con su periodicidad.
    var botonComprar = $("#btn-comprar");
    var notaCompra = $("#out-compra-nota");
    if (botonComprar) {
      botonComprar.href = "comprar.php?plan=" + plan +
        (plan === "personal" ? "&licencias=" + licencias + "&periodo=" + periodoActual : "");
      botonComprar.textContent = "Comprar " + datos.nombre + " en línea";
    }
    if (notaCompra) {
      var prorrateado = plan !== "personal" && periodoActual === "mensual";
      notaCompra.hidden = !prorrateado;
      notaCompra.textContent = prorrateado
        ? "El valor mensual es de referencia: el plan " + datos.nombre + " se paga por año (" + COP(datos.anual) + ")."
        : "";
    }

    ultimoCalculo = {
      clave: plan,
      plan: datos.nombre,
      licencias: licencias,
      periodo: periodoActual === "anual" ? "Anual (12 meses)" : "Mensual",
      total: total,
      unitario: unitario
    };
  }

  /* ---------------------------------------------------------------------
     5. Cotización descargable
     --------------------------------------------------------------------- */
  /*
   * La cotización se genera en el servidor (cotizacion.php) y se descarga en
   * PDF. El servidor recalcula con los precios del catálogo, así el documento
   * nunca lleva un precio distinto del que se cobra.
   */
  function descargarCotizacion() {
    if (!ultimoCalculo) { return; }
    var enlace = document.createElement("a");
    enlace.href = "cotizacion.php?plan=" + encodeURIComponent(ultimoCalculo.clave) +
      "&licencias=" + ultimoCalculo.licencias +
      "&periodo=" + (periodoActual === "anual" ? "anual" : "mensual");
    enlace.rel = "nofollow";
    document.body.appendChild(enlace);
    enlace.click();
    document.body.removeChild(enlace);
    if (API.aviso) { API.aviso("Preparando la cotización en PDF…"); }
  }

  /* ---------------------------------------------------------------------
     6. Eventos
     --------------------------------------------------------------------- */
  formulario.addEventListener("submit", function (ev) { ev.preventDefault(); });
  entradaLicencias.addEventListener("input", actualizar);
  if (rangoLicencias) {
    rangoLicencias.addEventListener("input", function () {
      entradaLicencias.value = rangoLicencias.value;
      actualizar();
    });
  }
  selectorPlan.addEventListener("change", actualizar);

  botonesPeriodo.forEach(function (boton) {
    boton.addEventListener("click", function () {
      periodoActual = boton.getAttribute("data-periodo");
      botonesPeriodo.forEach(function (b) {
        b.setAttribute("aria-pressed", String(b === boton));
      });
      actualizar();
    });
  });

  var botonDescargar = $("#btn-cotizacion");
  if (botonDescargar) { botonDescargar.addEventListener("click", descargarCotizacion); }

  var botonImprimir = $("#btn-imprimir");
  if (botonImprimir) { botonImprimir.addEventListener("click", function () { window.print(); }); }

  // Permite llegar con una cantidad preestablecida: precios.html?licencias=60
  var parametros = new URLSearchParams(window.location.search);
  if (parametros.has("licencias")) {
    entradaLicencias.value = parametros.get("licencias");
  }
  if (parametros.has("plan") && PLANES[parametros.get("plan")]) {
    selectorPlan.value = parametros.get("plan");
  }

  /* ---------------------------------------------------------------------
     7. Precios vigentes
     Las tarifas de arriba son las de fábrica. Las que valen son las que la
     administración fija en el portal: planes.js las trae y avisa.
     --------------------------------------------------------------------- */
  function aplicarPlanes(planes) {
    if (!planes) { return; }
    var per = planes.personal;
    if (per) {
      PLANES.personal.nombre = per.nombre;
      PLANES.personal.mensualPorLicencia = per.precio / per.meses;
      if (per.max_licencias) { PLANES.personal.maxLicencias = per.max_licencias; }
      if (per.meses_cobrados_anual) { PLANES.personal.mesesAnual = per.meses_cobrados_anual; }
    }
    ["escuela", "sitio"].forEach(function (k) {
      var p = planes[k];
      if (!p) { return; }
      PLANES[k].nombre = p.nombre;
      PLANES[k].anual = p.meses === 12 ? p.precio : p.precio * 12;
      PLANES[k].maxLicencias = p.cupo;
      PLANES[k].descripcion = PLANES[k].descripcion.replace(/\d+ licencias/, p.cupo + " licencias");
    });
    $$("option", selectorPlan).forEach(function (op) {
      if (planes[op.value]) { op.setAttribute("data-nombre", planes[op.value].nombre); }
    });
  }
  aplicarPlanes(window.VCodeProPlanes);
  document.addEventListener("vcodepro:planes", function (ev) {
    aplicarPlanes(ev.detail);
    actualizar();
  });

  actualizar();
})();
