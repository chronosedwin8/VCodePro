/* ==========================================================================
   Adjuntos de una entrega
   --------------------------------------------------------------------------
   Sube archivos sin recargar la página y los va añadiendo a la lista. Si el
   JavaScript no carga, la lista de adjuntos ya subidos se sigue viendo y
   descargando: lo único que se pierde es poder añadir más desde aquí.

   Los archivos se envían de uno en uno para poder decir cuál falló y por qué,
   en vez de dar un error global cuando el estudiante arrastra seis.
   ========================================================================== */
(function () {
  "use strict";

  function iniciar() {
    document.querySelectorAll("[data-adj]").forEach(montar);
  }

  function montar(caja) {
    if (caja.dataset.adjListo) return;
    caja.dataset.adjListo = "1";

    var lista  = caja.querySelector("[data-adj-lista]");
    var zona   = caja.querySelector("[data-adj-zona]");
    var campo  = caja.querySelector("[data-adj-campo]");
    var estado = caja.querySelector("[data-adj-estado]");

    // Los botones de quitar existen aunque no se pueda subir nada nuevo.
    if (lista) {
      lista.addEventListener("click", function (e) {
        var b = e.target.closest("[data-quitar]");
        if (b) quitar(b.dataset.quitar, b.closest("li"));
      });
    }

    if (!zona || !campo) return;

    campo.addEventListener("change", function () {
      enviarVarios(campo.files);
      campo.value = "";              // permite volver a elegir el mismo archivo
    });

    ["dragenter", "dragover"].forEach(function (ev) {
      zona.addEventListener(ev, function (e) {
        e.preventDefault();
        zona.classList.add("es-encima");
      });
    });
    ["dragleave", "drop"].forEach(function (ev) {
      zona.addEventListener(ev, function (e) {
        e.preventDefault();
        zona.classList.remove("es-encima");
      });
    });
    zona.addEventListener("drop", function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) enviarVarios(e.dataTransfer.files);
    });

    function decir(texto, clase) {
      if (!estado) return;
      estado.textContent = texto;
      estado.className = "adj-estado" + (clase ? " " + clase : "");
    }

    function enviarVarios(archivos) {
      var pendientes = Array.prototype.slice.call(archivos);
      if (!pendientes.length) return;
      var fallos = 0;

      (function siguiente() {
        if (!pendientes.length) {
          decir(fallos ? "Terminado, con " + fallos + " archivo(s) sin subir."
                       : "Listo.", fallos ? "es-error" : "es-ok");
          return;
        }
        var archivo = pendientes.shift();
        decir("Subiendo " + archivo.name + "… (quedan " + (pendientes.length + 1) + ")");
        enviar(archivo, function (ok, mensaje) {
          if (!ok) { fallos++; decir(archivo.name + ": " + mensaje, "es-error"); }
          siguiente();
        });
      })();
    }

    function enviar(archivo, hecho) {
      var f = new FormData();
      f.append("_csrf", caja.dataset.csrf || "");
      f.append("accion", "subir");
      f.append("entrega_id", caja.dataset.entrega || "");
      f.append("fase_id", caja.dataset.fase || "");
      f.append("archivo", archivo);

      fetch(caja.dataset.url, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: "Respuesta inesperada del servidor (" + r.status + ")." }; }); })
        .then(function (d) {
          if (d.ok && d.adjunto) { pintar(d.adjunto); hecho(true, ""); }
          else hecho(false, d.error || "No se pudo subir.");
        })
        .catch(function () { hecho(false, "Sin conexión con el servidor."); });
    }

    function pintar(a) {
      if (!lista) return;
      var li = document.createElement("li");
      li.dataset.id = a.id;
      var tipo = document.createElement("span");
      tipo.className = "adj-tipo";
      tipo.textContent = a.etiqueta;
      var enlace = document.createElement("a");
      enlace.href = a.url;
      enlace.textContent = a.nombre;
      var peso = document.createElement("span");
      peso.className = "adj-peso";
      peso.textContent = a.peso;
      li.appendChild(tipo); li.appendChild(enlace); li.appendChild(peso);
      if (a.borrable) {
        var b = document.createElement("button");
        b.type = "button";
        b.className = "adj-quitar";
        b.dataset.quitar = a.id;
        b.title = "Quitar";
        b.setAttribute("aria-label", "Quitar " + a.nombre);
        b.innerHTML = "&times;";
        li.appendChild(b);
      }
      lista.appendChild(li);
    }

    function quitar(id, li) {
      if (!window.confirm("¿Quitar este archivo de la entrega?")) return;
      var f = new FormData();
      f.append("_csrf", caja.dataset.csrf || "");
      f.append("accion", "borrar");
      f.append("entrega_id", caja.dataset.entrega || "");
      f.append("id", id);
      decir("Quitando…");
      fetch(caja.dataset.url, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          if (d.ok) { if (li) li.remove(); decir("Archivo quitado.", "es-ok"); }
          else decir(d.error || "No se pudo quitar.", "es-error");
        })
        .catch(function () { decir("Sin conexión con el servidor.", "es-error"); });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
