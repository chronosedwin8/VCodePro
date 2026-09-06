/* ==========================================================================
   Asistente de IA
   --------------------------------------------------------------------------
   Calificación de un grupo entero desde un botón, y redacción asistida de una
   actividad nueva.

   Las entregas se califican **de una en una**, en serie: cada llamada al
   modelo tarda varios segundos y una sola petición para treinta estudiantes se
   pasaría del tiempo máximo de ejecución de PHP. Yendo así, el docente ve por
   dónde va, puede parar, y si una falla se sabe cuál y por qué.
   ========================================================================== */
(function () {
  "use strict";

  /* ------------------------------------------ calificación de un grupo -- */

  function montarCalificacion(boton) {
    var barra    = document.querySelector("[data-ia-progreso]");
    var estado   = document.querySelector("[data-ia-estado]");
    var corriendo = false;
    var cancelar  = false;

    function decir(texto, clase) {
      if (!estado) return;
      estado.textContent = texto;
      estado.className = "pista" + (clase ? " " + clase : "");
    }

    function avance(hechas, total) {
      if (!barra) return;
      barra.hidden = false;
      barra.querySelector("i").style.width = total ? (hechas / total) * 100 + "%" : "0%";
    }

    boton.addEventListener("click", function () {
      if (corriendo) {                       // segundo clic: pedir parada
        cancelar = true;
        decir("Parando al terminar el estudiante en curso…");
        return;
      }

      var filas = Array.prototype.slice.call(document.querySelectorAll("[data-fila]"))
        .filter(function (tr) {
          var estadoTxt = tr.children[2] ? tr.children[2].textContent.toLowerCase() : "";
          // Ya publicadas no se vuelven a calificar.
          return estadoTxt.indexOf("calificad") === -1 && estadoTxt.indexOf("revisad") === -1;
        });

      if (!filas.length) { decir("No hay entregas pendientes de calificar.", "es-ok"); return; }

      corriendo = true;
      cancelar  = false;
      boton.textContent = "Detener";
      boton.classList.add("btn-ghost");
      boton.removeAttribute("data-confirmar");

      var total = filas.length, hechas = 0, fallos = 0;
      avance(0, total);

      (function siguiente() {
        if (cancelar || !filas.length) return terminar();

        var tr = filas.shift();
        var id = tr.getAttribute("data-fila");
        var nombre = tr.children[1] ? tr.children[1].textContent.trim() : ("#" + id);
        tr.classList.add("es-procesando");
        decir("Calificando a " + nombre + "… (" + (hechas + 1) + " de " + total + ")");

        var f = new FormData();
        f.append("_csrf", boton.dataset.csrf || "");
        f.append("entrega_id", id);

        fetch(boton.dataset.url, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } })
          .then(function (r) {
            return r.json().catch(function () {
              return { ok: false, error: "El servidor respondió algo ilegible (" + r.status + ")." };
            });
          })
          .then(function (d) {
            tr.classList.remove("es-procesando");
            hechas++;
            if (d.ok && d.resultado) {
              pintarPuntaje(tr, d.resultado);
              tr.classList.add("es-listo");
            } else {
              fallos++;
              tr.classList.add("es-fallo");
              tr.title = d.error || "No se pudo calificar.";
            }
            avance(hechas, total);
            siguiente();
          })
          .catch(function () {
            tr.classList.remove("es-procesando");
            tr.classList.add("es-fallo");
            hechas++; fallos++;
            avance(hechas, total);
            siguiente();
          });
      })();

      function terminar() {
        corriendo = false;
        boton.textContent = "Calificar todo el grupo con IA";
        boton.classList.remove("btn-ghost");
        var msg = cancelar ? "Detenido. " : "";
        msg += hechas + " de " + total + " calificadas";
        msg += fallos ? ", " + fallos + " con error (pasa el cursor por la fila)." : ".";
        msg += " Recarga la página para revisarlas y publicarlas.";
        decir(msg, fallos ? "es-error" : "es-ok");
      }
    });

    function pintarPuntaje(tr, r) {
      var celda = tr.querySelector("[data-celda-puntaje]");
      if (!celda) return;
      celda.innerHTML = "";
      var fuerte = document.createElement("strong");
      fuerte.textContent = r.obtenido;
      var resto = document.createTextNode(" / " + r.maximo + " ");
      var nota = document.createElement("span");
      nota.className = "txt-sm txt-muted";
      nota.textContent = "· nota " + r.nota;
      celda.appendChild(fuerte); celda.appendChild(resto); celda.appendChild(nota);
    }
  }

  /**
   * Saneado del HTML que devuelve el modelo antes de meterlo en el editor.
   * El servidor lo vuelve a sanear al guardar, pero aquí importa igual: un
   * <img onerror> insertado con innerHTML se ejecuta en el acto.
   */
  function saneado(html) {
    var PERMITIDAS = ["P", "BR", "STRONG", "EM", "U", "S", "UL", "OL", "LI",
                      "H3", "H4", "BLOCKQUOTE", "CODE", "PRE"];
    var caja = document.createElement("div");
    caja.innerHTML = String(html || "");
    (function limpiar(nodo) {
      Array.prototype.slice.call(nodo.childNodes).forEach(function (hijo) {
        if (hijo.nodeType === 3) return;
        if (hijo.nodeType !== 1) { hijo.remove(); return; }
        limpiar(hijo);
        if (PERMITIDAS.indexOf(hijo.tagName.toUpperCase()) === -1) {
          var padre = hijo.parentNode;
          while (hijo.firstChild) padre.insertBefore(hijo.firstChild, hijo);
          padre.removeChild(hijo);
          return;
        }
        Array.prototype.slice.call(hijo.attributes).forEach(function (a) {
          hijo.removeAttribute(a.name);
        });
      });
    })(caja);
    return caja.innerHTML;
  }

  /* --------------------------------- redacción de una actividad nueva --- */

  function montarRedaccion(boton) {
    var estado = document.querySelector("[data-ia-redaccion-estado]");

    function decir(t, c) {
      if (!estado) return;
      estado.textContent = t;
      estado.className = "pista" + (c ? " " + c : "");
    }

    boton.addEventListener("click", function () {
      var tema = (document.querySelector("[data-ia-tema]") || {}).value || "";
      if (!tema.trim()) { decir("Escribe primero de qué trata la actividad.", "es-error"); return; }

      boton.disabled = true;
      decir("Redactando… esto tarda unos segundos.");

      var f = new FormData();
      f.append("_csrf", boton.dataset.csrf || "");
      f.append("tema", tema);
      var nivel = document.querySelector("[data-ia-nivel]");
      if (nivel) f.append("nivel_id", nivel.value);

      fetch(boton.dataset.url, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } })
        .then(function (r) {
          return r.json().catch(function () { return { ok: false, error: "Respuesta ilegible." }; });
        })
        .then(function (d) {
          boton.disabled = false;
          if (!d.ok) { decir(d.error || "No se pudo redactar.", "es-error"); return; }
          rellenar(d.actividad);
          decir("Borrador listo. Revísalo, ajústalo y guarda.", "es-ok");
        })
        .catch(function () { boton.disabled = false; decir("Sin conexión con el servidor.", "es-error"); });
    });

    /** Vuelca el borrador en el formulario, sin pisar lo que ya esté escrito. */
    function rellenar(a) {
      Object.keys(a || {}).forEach(function (campo) {
        var el = document.querySelector('[name="' + campo + '"]');
        if (!el || el.type === "hidden") return;
        var valor = a[campo];
        if (valor === null || valor === undefined || valor === "") return;

        var caja = el.closest(".rico");
        if (caja) {                                   // campo con editor enriquecido
          var area = caja.querySelector(".rico-area");
          if (area) {
            area.innerHTML = saneado(valor);
            area.dispatchEvent(new Event("input", { bubbles: true }));
            area.classList.remove("es-vacio");
          }
          return;
        }
        if (el.tagName === "SELECT") {
          var opcion = Array.prototype.slice.call(el.options).filter(function (o) {
            return o.value === String(valor) || o.textContent.trim() === String(valor);
          })[0];
          if (opcion) el.value = opcion.value;
          return;
        }
        el.value = valor;
      });
      // Las fases llegan aparte porque son una lista.
      if (a && a.fases && a.fases.length) volcarFases(a.fases);
    }

    /** Cada fase va a su bloque del formulario, según su etapa del ciclo. */
    function volcarFases(fases) {
      fases.forEach(function (f) {
        poner('fase_titulo_' + f.fase, f.titulo);
        poner('fase_instr_' + f.fase, f.instrucciones);
        poner('fase_evid_' + f.fase, f.entregable);
        poner('fase_min_' + f.fase, f.minutos);
      });
    }

    /** Escribe en un campo, sea textarea normal o editor enriquecido. */
    function poner(nombre, valor) {
      var el = document.querySelector('[name="' + nombre + '"]');
      if (!el || valor === undefined || valor === null || valor === "") return;
      var caja = el.closest(".rico");
      if (caja) {
        var area = caja.querySelector(".rico-area");
        if (!area) return;
        area.textContent = String(valor);       // texto plano: el editor lo formatea
        area.dispatchEvent(new Event("input", { bubbles: true }));
        area.classList.remove("es-vacio");
        return;
      }
      el.value = valor;
    }
  }

  function iniciar() {
    var calif = document.querySelector("[data-ia-lanzar]");
    if (calif) montarCalificacion(calif);
    var redac = document.querySelector("[data-ia-redactar]");
    if (redac) montarRedaccion(redac);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
