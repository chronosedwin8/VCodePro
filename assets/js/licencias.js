/* =============================================================================
   VCodePro — Portal de licencias (JavaScript puro)

   Incluye tres módulos:
     A. Generador de claves de demostración (codifica plan, cupo y vigencia).
     B. Validador y activador de licencias (verifica el dígito de control).
     C. Panel de puestos: asignación de licencias a docentes y estudiantes.

   Todos los datos se guardan únicamente en el navegador (localStorage).
   No se envía información a ningún servidor.
   ============================================================================= */
(function () {
  "use strict";

  var API = window.VCodePro || {};
  var $ = API.$ || function (s, c) { return (c || document).querySelector(s); };
  var $$ = API.$$ || function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var aviso = API.aviso || function (m) { window.alert(m); };

  if (!$("#licencias-app")) { return; }

  /* =====================================================================
     0. Definición de planes
     ===================================================================== */
  var PLANES = {
    P: { id: "P", nombre: "Personal",           cupo: 1,   maxCupo: 10,  precio: "150.000 COP al mes" },
    E: { id: "E", nombre: "Escuela",            cupo: 100, maxCupo: 100, precio: "5.000.000 COP al año" },
    S: { id: "S", nombre: "Licencia de Sitio",  cupo: 500, maxCupo: 500, precio: "20.000.000 COP al año" }
  };

  var CLAVE_LICENCIA = "vcodepro-licencia";
  var CLAVE_PUESTOS = "vcodepro-puestos";
  var ALFABETO = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

  /* =====================================================================
     1. Utilidades de almacenamiento
     ===================================================================== */
  function guardar(clave, valor) {
    try {
      localStorage.setItem(clave, JSON.stringify(valor));
      return true;
    } catch (e) {
      aviso("El navegador bloqueó el almacenamiento local; los datos no se conservarán.");
      return false;
    }
  }

  function leer(clave, porDefecto) {
    try {
      var crudo = localStorage.getItem(clave);
      return crudo ? JSON.parse(crudo) : porDefecto;
    } catch (e) {
      return porDefecto;
    }
  }

  function borrar(clave) {
    try { localStorage.removeItem(clave); } catch (e) { /* sin acción */ }
  }

  /* =====================================================================
     2. Codificación de claves
     Formato: VCP-<plan+vigencia>-<cupo>-<serie>-<control>
     Ejemplo: VCP-E27C-02S4-9HKD-4B7Q
     ===================================================================== */
  function aBase36(numero, largo) {
    var texto = Math.abs(Math.floor(numero)).toString(36).toUpperCase();
    while (texto.length < largo) { texto = "0" + texto; }
    return texto.slice(-largo);
  }

  function aleatorio(largo) {
    var salida = "";
    var buffer;
    if (window.crypto && window.crypto.getRandomValues) {
      buffer = new Uint8Array(largo);
      window.crypto.getRandomValues(buffer);
      for (var i = 0; i < largo; i++) { salida += ALFABETO[buffer[i] % 36]; }
    } else {
      for (var j = 0; j < largo; j++) { salida += ALFABETO[Math.floor(Math.random() * 36)]; }
    }
    return salida;
  }

  // Dígito de control: hash determinista de los tres primeros segmentos.
  function control(segmentos) {
    var texto = segmentos.join("");
    var h = 5381;
    for (var i = 0; i < texto.length; i++) {
      h = ((h * 33) ^ texto.charCodeAt(i)) >>> 0;
    }
    return aBase36(h % Math.pow(36, 4), 4);
  }

  function generarClave(planId, cupo, vence) {
    var anio = vence.getFullYear() % 100;          // dos dígitos
    var mes = ALFABETO[vence.getMonth() + 1];      // 1 = enero … C = diciembre
    var s1 = planId + aBase36(anio, 2) + mes;      // p. ej. E27C
    var s2 = aBase36(cupo, 3) + aleatorio(1);      // cupo codificado
    var s3 = aleatorio(4);                         // serie
    var s4 = control([s1, s2, s3]);
    return ["VCP", s1, s2, s3, s4].join("-");
  }

  function normalizar(texto) {
    return String(texto || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
  }

  function descomponer(texto) {
    var limpio = normalizar(texto);
    if (limpio.indexOf("VCP") === 0) { limpio = limpio.slice(3); }
    if (limpio.length !== 16) { return null; }
    return [limpio.slice(0, 4), limpio.slice(4, 8), limpio.slice(8, 12), limpio.slice(12, 16)];
  }

  function validarClave(texto) {
    var partes = descomponer(texto);
    if (!partes) {
      return { valida: false, motivo: "La clave debe tener 16 caracteres, con el formato VCP-XXXX-XXXX-XXXX-XXXX." };
    }

    var s1 = partes[0];
    var plan = PLANES[s1.charAt(0)];
    if (!plan) {
      return { valida: false, motivo: "El tipo de plan de la clave no corresponde a ningún producto VCodePro." };
    }

    if (control([partes[0], partes[1], partes[2]]) !== partes[3]) {
      return { valida: false, motivo: "El dígito de control no coincide: la clave fue digitada mal o no es auténtica." };
    }

    var anio = 2000 + parseInt(s1.slice(1, 3), 36);
    var mes = ALFABETO.indexOf(s1.charAt(3));
    if (mes < 1 || mes > 12) {
      return { valida: false, motivo: "La fecha de vigencia codificada no es válida." };
    }
    var vence = new Date(anio, mes, 0, 23, 59, 59);   // último día del mes indicado
    var cupo = parseInt(partes[1].slice(0, 3), 36);
    if (!cupo || cupo > plan.maxCupo) {
      return { valida: false, motivo: "El cupo codificado supera el máximo permitido para el plan " + plan.nombre + "." };
    }

    return {
      valida: true,
      plan: plan,
      cupo: cupo,
      vence: vence,
      vencida: vence.getTime() < Date.now(),
      clave: ["VCP", partes[0], partes[1], partes[2], partes[3]].join("-")
    };
  }

  /* =====================================================================
     3. Fechas
     ===================================================================== */
  function formatoFecha(fecha) {
    return new Date(fecha).toLocaleDateString("es-CO", { day: "2-digit", month: "long", year: "numeric" });
  }

  function diasRestantes(fecha) {
    return Math.ceil((new Date(fecha).getTime() - Date.now()) / 86400000);
  }

  function sumarMeses(fecha, meses) {
    var d = new Date(fecha.getTime());
    d.setMonth(d.getMonth() + meses);
    return d;
  }

  /* =====================================================================
     A. Generador de claves de demostración
     ===================================================================== */
  var genPlan = $("#gen-plan");
  var genInstitucion = $("#gen-institucion");
  var genMeses = $("#gen-meses");
  var genCupo = $("#gen-cupo");
  var campoMeses = $("#campo-meses");
  var campoCupo = $("#campo-cupo");

  function sincronizarGenerador() {
    if (!genPlan) { return; }
    var plan = PLANES[genPlan.value];
    var esPersonal = plan.id === "P";
    if (campoMeses) { campoMeses.hidden = !esPersonal; }
    if (campoCupo) { campoCupo.hidden = esPersonal; }
    if (genCupo) {
      genCupo.value = plan.cupo;
      genCupo.max = plan.maxCupo;
    }
  }

  function alGenerar(ev) {
    ev.preventDefault();
    var plan = PLANES[genPlan.value];
    var meses = plan.id === "P" ? parseInt(genMeses.value, 10) || 1 : 12;
    var cupo = plan.id === "P" ? 1 : plan.cupo;
    var vence = sumarMeses(new Date(), meses);
    var clave = generarClave(plan.id, cupo, vence);

    $("#gen-resultado").hidden = false;
    $("#gen-clave").textContent = clave;
    $("#gen-detalle").textContent =
      plan.nombre + " · " + cupo + (cupo === 1 ? " licencia" : " licencias") +
      " · vigente hasta " + formatoFecha(vence) +
      (genInstitucion && genInstitucion.value ? " · " + genInstitucion.value : "");

    var pasar = $("#btn-usar-clave");
    if (pasar) {
      pasar.onclick = function () {
        var campo = $("#act-clave");
        campo.value = clave;
        if (genInstitucion && genInstitucion.value && $("#act-institucion")) {
          $("#act-institucion").value = genInstitucion.value;
        }
        campo.focus();
        campo.scrollIntoView({ behavior: "smooth", block: "center" });
      };
    }
  }

  if (genPlan) {
    genPlan.addEventListener("change", sincronizarGenerador);
    sincronizarGenerador();
  }
  var formGenerador = $("#gen-form");
  if (formGenerador) { formGenerador.addEventListener("submit", alGenerar); }

  /* =====================================================================
     B. Activación de la licencia
     ===================================================================== */
  var formActivar = $("#act-form");
  var campoClave = $("#act-clave");

  function mostrarEstado(tipo, mensaje) {
    var caja = $("#act-estado");
    if (!caja) { return; }
    caja.hidden = false;
    caja.className = "badge " + tipo;
    caja.textContent = mensaje;
  }

  function activar(ev) {
    ev.preventDefault();
    var resultado = validarClave(campoClave.value);

    if (!resultado.valida) {
      mostrarEstado("err", "Clave no válida");
      $("#act-motivo").hidden = false;
      $("#act-motivo").textContent = resultado.motivo;
      return;
    }

    if (resultado.vencida) {
      mostrarEstado("warn", "Licencia vencida");
      $("#act-motivo").hidden = false;
      $("#act-motivo").textContent = "Esta licencia venció el " + formatoFecha(resultado.vence) +
        ". Renuévala para seguir recibiendo actualizaciones y soporte.";
      return;
    }

    var institucion = $("#act-institucion") ? $("#act-institucion").value.trim() : "";
    var licencia = {
      clave: resultado.clave,
      plan: resultado.plan.id,
      planNombre: resultado.plan.nombre,
      cupo: resultado.cupo,
      vence: resultado.vence.toISOString(),
      institucion: institucion || "Sin institución registrada",
      activada: new Date().toISOString()
    };

    guardar(CLAVE_LICENCIA, licencia);
    mostrarEstado("ok", "Licencia activada");
    $("#act-motivo").hidden = true;
    aviso("Licencia " + resultado.plan.nombre + " activada correctamente.");
    pintarPanel();
    var panel = $("#panel");
    if (panel) { panel.scrollIntoView({ behavior: "smooth", block: "start" }); }
  }

  if (formActivar) { formActivar.addEventListener("submit", activar); }

  // Da formato automático a la clave mientras se escribe.
  if (campoClave) {
    campoClave.addEventListener("input", function () {
      var limpio = normalizar(campoClave.value);
      if (limpio.indexOf("VCP") === 0) { limpio = limpio.slice(3); }
      limpio = limpio.slice(0, 16);
      var grupos = limpio.match(/.{1,4}/g) || [];
      campoClave.value = grupos.length ? "VCP-" + grupos.join("-") : "";
    });
  }

  var botonVerificar = $("#btn-verificar");
  if (botonVerificar) {
    botonVerificar.addEventListener("click", function () {
      var r = validarClave(campoClave.value);
      if (r.valida) {
        mostrarEstado(r.vencida ? "warn" : "ok", r.vencida ? "Formato correcto, licencia vencida" : "Formato correcto");
        $("#act-motivo").hidden = false;
        $("#act-motivo").textContent = r.plan.nombre + " · " + r.cupo +
          (r.cupo === 1 ? " licencia" : " licencias") + " · vigente hasta " + formatoFecha(r.vence);
      } else {
        mostrarEstado("err", "Clave no válida");
        $("#act-motivo").hidden = false;
        $("#act-motivo").textContent = r.motivo;
      }
    });
  }

  /* =====================================================================
     C. Panel: puestos asignados
     ===================================================================== */
  function puestos() {
    return leer(CLAVE_PUESTOS, []);
  }

  function pintarPanel() {
    var panel = $("#panel");
    var vacio = $("#panel-vacio");
    var licencia = leer(CLAVE_LICENCIA, null);

    if (!licencia) {
      if (panel) { panel.hidden = true; }
      if (vacio) { vacio.hidden = false; }
      return;
    }

    if (panel) { panel.hidden = false; }
    if (vacio) { vacio.hidden = true; }

    var dias = diasRestantes(licencia.vence);
    var lista = puestos();

    $("#pnl-plan").textContent = licencia.planNombre;
    $("#pnl-clave").textContent = licencia.clave;
    $("#pnl-institucion").textContent = licencia.institucion;
    $("#pnl-activada").textContent = formatoFecha(licencia.activada);
    $("#pnl-vence").textContent = formatoFecha(licencia.vence) +
      (dias >= 0 ? " (" + dias + (dias === 1 ? " día restante)" : " días restantes)") : " (vencida)");
    $("#pnl-cupo").textContent = lista.length + " de " + licencia.cupo;

    var porcentaje = Math.min(100, Math.round((lista.length / licencia.cupo) * 100));
    $("#pnl-barra").style.width = porcentaje + "%";
    $("#pnl-barra-texto").textContent = porcentaje + "% del cupo asignado";

    var estado = $("#pnl-estado");
    if (dias < 0) {
      estado.className = "badge err";
      estado.textContent = "Vencida";
    } else if (dias <= 30) {
      estado.className = "badge warn";
      estado.textContent = "Por renovar";
    } else {
      estado.className = "badge ok";
      estado.textContent = "Activa";
    }

    var cuerpo = $("#seat-tbody");
    if (!lista.length) {
      cuerpo.innerHTML = '<tr class="empty-row"><td colspan="5">Todavía no hay licencias asignadas. ' +
        "Agrega el primer docente o estudiante con el formulario de arriba.</td></tr>";
    } else {
      cuerpo.innerHTML = lista.map(function (p, indice) {
        return "<tr>" +
          "<td>" + (indice + 1) + "</td>" +
          "<td>" + escapar(p.nombre) + "</td>" +
          "<td>" + escapar(p.correo) + "</td>" +
          "<td>" + escapar(p.rol) + "</td>" +
          '<td><button type="button" class="del" data-id="' + p.id + '" ' +
          'aria-label="Liberar la licencia de ' + escapar(p.nombre) + '">Liberar</button></td>' +
          "</tr>";
      }).join("");
    }

    var formPuesto = $("#seat-form");
    if (formPuesto) {
      var lleno = lista.length >= licencia.cupo;
      $$("input, select, button", formPuesto).forEach(function (el) { el.disabled = lleno; });
      $("#seat-lleno").hidden = !lleno;
    }
  }

  function escapar(texto) {
    return String(texto)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  var formPuestos = $("#seat-form");
  if (formPuestos) {
    formPuestos.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var licencia = leer(CLAVE_LICENCIA, null);
      if (!licencia) { return; }

      var nombre = $("#seat-nombre").value.trim();
      var correo = $("#seat-correo").value.trim();
      var rol = $("#seat-rol").value;
      if (!nombre || !correo) { return; }

      var lista = puestos();
      if (lista.length >= licencia.cupo) {
        aviso("El cupo del plan " + licencia.planNombre + " está completo.");
        return;
      }
      if (lista.some(function (p) { return p.correo.toLowerCase() === correo.toLowerCase(); })) {
        aviso("Ese correo ya tiene una licencia asignada.");
        return;
      }

      lista.push({
        id: "u" + Date.now().toString(36) + aleatorio(3),
        nombre: nombre,
        correo: correo,
        rol: rol,
        fecha: new Date().toISOString()
      });
      guardar(CLAVE_PUESTOS, lista);
      formPuestos.reset();
      $("#seat-nombre").focus();
      pintarPanel();
      aviso("Licencia asignada a " + nombre + ".");
    });
  }

  var cuerpoTabla = $("#seat-tbody");
  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", function (ev) {
      var boton = ev.target.closest("button.del");
      if (!boton) { return; }
      var id = boton.getAttribute("data-id");
      var lista = puestos().filter(function (p) { return p.id !== id; });
      guardar(CLAVE_PUESTOS, lista);
      pintarPanel();
      aviso("Licencia liberada.");
    });
  }

  /* ---------- Exportar a CSV ---------- */
  var botonCsv = $("#btn-csv");
  if (botonCsv) {
    botonCsv.addEventListener("click", function () {
      var licencia = leer(CLAVE_LICENCIA, null);
      var lista = puestos();
      if (!licencia || !lista.length) {
        aviso("No hay licencias asignadas para exportar.");
        return;
      }
      var filas = [["#", "Nombre", "Correo", "Rol", "Fecha de asignación", "Clave", "Plan"]];
      lista.forEach(function (p, i) {
        filas.push([i + 1, p.nombre, p.correo, p.rol, formatoFecha(p.fecha), licencia.clave, licencia.planNombre]);
      });
      var csv = filas.map(function (fila) {
        return fila.map(function (celda) {
          return '"' + String(celda).replace(/"/g, '""') + '"';
        }).join(";");
      }).join("\r\n");

      var blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
      var url = URL.createObjectURL(blob);
      var enlace = document.createElement("a");
      enlace.href = url;
      enlace.download = "licencias-vcodepro.csv";
      document.body.appendChild(enlace);
      enlace.click();
      document.body.removeChild(enlace);
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
      aviso("Listado exportado en formato CSV.");
    });
  }

  /* ---------- Cerrar la licencia en este equipo ---------- */
  var botonCerrar = $("#btn-desactivar");
  if (botonCerrar) {
    botonCerrar.addEventListener("click", function () {
      if (!window.confirm("¿Desactivar la licencia en este equipo? Se borrará el listado de puestos guardado en este navegador.")) {
        return;
      }
      borrar(CLAVE_LICENCIA);
      borrar(CLAVE_PUESTOS);
      var estado = $("#act-estado");
      if (estado) { estado.hidden = true; }
      var motivo = $("#act-motivo");
      if (motivo) { motivo.hidden = true; }
      if (campoClave) { campoClave.value = ""; }
      pintarPanel();
      aviso("Licencia desactivada en este equipo.");
    });
  }

  pintarPanel();
})();
