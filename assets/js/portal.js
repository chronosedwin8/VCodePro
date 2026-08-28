/* ==========================================================================
   VCodePro — Portal académico
   JavaScript sin dependencias: tema, menú, menús desplegables, confirmaciones,
   guardado automático de las fases del ciclo de diseño y filtros de tabla.
   ========================================================================== */
(function () {
  "use strict";

  var raiz = document.documentElement;

  /* ---------- Tema claro / oscuro ---------- */
  function aplicarTema(t) {
    raiz.setAttribute("data-theme", t);
    try { localStorage.setItem("vcodepro-tema", t); } catch (e) {}
    document.querySelectorAll(".theme-toggle").forEach(function (b) {
      b.setAttribute("aria-pressed", t === "dark" ? "true" : "false");
      b.setAttribute("aria-label", t === "dark" ? "Cambiar a tema claro" : "Cambiar a tema oscuro");
    });
  }
  aplicarTema(raiz.getAttribute("data-theme") || "dark");

  document.querySelectorAll(".theme-toggle").forEach(function (b) {
    b.addEventListener("click", function () {
      var nuevo = raiz.getAttribute("data-theme") === "dark" ? "light" : "dark";
      aplicarTema(nuevo);
      // Persistir la preferencia en el perfil del usuario.
      var f = new FormData();
      f.append("tema", nuevo);
      fetch(document.body.dataset.api ? document.body.dataset.api + "/tema.php" : "", { method: "POST", body: f })
        .catch(function () {});
    });
  });

  /* ---------- Menú lateral en móvil ---------- */
  var burger = document.getElementById("pt-burger");
  var side = document.getElementById("pt-side");
  var velo = null;

  function cerrarLateral() {
    if (!side) return;
    side.classList.remove("abierto");
    burger.setAttribute("aria-expanded", "false");
    if (velo) { velo.remove(); velo = null; }
  }

  if (burger && side) {
    burger.addEventListener("click", function () {
      var abierto = side.classList.toggle("abierto");
      burger.setAttribute("aria-expanded", abierto ? "true" : "false");
      if (abierto) {
        velo = document.createElement("div");
        velo.className = "pt-velo";
        velo.addEventListener("click", cerrarLateral);
        document.body.appendChild(velo);
      } else { cerrarLateral(); }
    });
  }

  /* ---------- Menús desplegables ---------- */
  document.querySelectorAll("[data-menu]").forEach(function (caja) {
    var boton = caja.querySelector("button");
    var panel = caja.querySelector(".pt-drop");
    if (!boton || !panel) return;
    boton.addEventListener("click", function (e) {
      e.stopPropagation();
      var abierto = !panel.hidden;
      panel.hidden = abierto;
      boton.setAttribute("aria-expanded", abierto ? "false" : "true");
    });
  });
  document.addEventListener("click", function () {
    document.querySelectorAll(".pt-drop").forEach(function (p) {
      p.hidden = true;
      var b = p.parentElement && p.parentElement.querySelector("button");
      if (b) b.setAttribute("aria-expanded", "false");
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      cerrarLateral();
      document.querySelectorAll(".pt-drop").forEach(function (p) { p.hidden = true; });
    }
  });

  /* ---------- Confirmación antes de acciones destructivas ---------- */
  document.querySelectorAll("[data-confirmar]").forEach(function (el) {
    el.addEventListener("click", function (e) {
      if (!window.confirm(el.getAttribute("data-confirmar"))) e.preventDefault();
    });
  });

  /* ---------- Copiar al portapapeles ---------- */
  document.querySelectorAll("[data-copiar]").forEach(function (el) {
    el.classList.add("copiar");
    el.addEventListener("click", function () {
      var texto = el.getAttribute("data-copiar");
      var previo = el.textContent;
      var listo = function () {
        el.textContent = "¡Copiado!";
        setTimeout(function () { el.textContent = previo; }, 1400);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(listo, function () {});
      } else {
        var ta = document.createElement("textarea");
        ta.value = texto; document.body.appendChild(ta); ta.select();
        try { document.execCommand("copy"); listo(); } catch (e) {}
        ta.remove();
      }
    });
  });

  /* ---------- Filtro de tabla en vivo ---------- */
  document.querySelectorAll("[data-filtra]").forEach(function (input) {
    var tabla = document.querySelector(input.getAttribute("data-filtra"));
    if (!tabla) return;
    input.addEventListener("input", function () {
      var t = input.value.trim().toLowerCase();
      var visibles = 0;
      tabla.querySelectorAll("tbody tr").forEach(function (tr) {
        var ok = tr.textContent.toLowerCase().indexOf(t) !== -1;
        tr.hidden = !ok;
        if (ok) visibles++;
      });
      var cuenta = document.querySelector(input.getAttribute("data-cuenta") || "#nada");
      if (cuenta) cuenta.textContent = visibles;
    });
  });

  /* ---------- Guardado automático de las fases del ciclo de diseño ---------- */
  var temporizadores = {};
  document.querySelectorAll("textarea[data-fase]").forEach(function (ta) {
    var estado = document.querySelector('[data-estado-fase="' + ta.dataset.fase + '"]');
    ta.addEventListener("input", function () {
      if (estado) estado.textContent = "Escribiendo…";
      clearTimeout(temporizadores[ta.dataset.fase]);
      temporizadores[ta.dataset.fase] = setTimeout(function () { guardarFase(ta, estado); }, 1200);
    });
    ta.addEventListener("blur", function () {
      clearTimeout(temporizadores[ta.dataset.fase]);
      guardarFase(ta, estado);
    });
  });

  function guardarFase(ta, estado) {
    var url = ta.dataset.url;
    if (!url) return;
    var f = new FormData();
    f.append("_csrf", ta.dataset.csrf || "");
    f.append("fase_id", ta.dataset.fase);
    f.append("entrega_id", ta.dataset.entrega || "");
    f.append("contenido", ta.value);
    if (estado) estado.textContent = "Guardando…";
    fetch(url, { method: "POST", body: f, headers: { "X-Requested-With": "fetch" } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (estado) estado.textContent = d.ok ? "Guardado " + d.hora : "No se pudo guardar";
        if (d.ok && typeof d.progreso === "number") {
          var b = document.querySelector("[data-progreso]");
          if (b) {
            b.querySelector("i").style.width = d.progreso + "%";
            b.setAttribute("aria-valuenow", d.progreso);
            var et = document.querySelector("[data-progreso-texto]");
            if (et) et.textContent = d.progreso + "%";
          }
        }
      })
      .catch(function () { if (estado) estado.textContent = "Sin conexión"; });
  }

  /* ---------- Marcar fase como completada ---------- */
  document.querySelectorAll("[data-completar]").forEach(function (chk) {
    chk.addEventListener("change", function () {
      var f = new FormData();
      f.append("_csrf", chk.dataset.csrf || "");
      f.append("fase_id", chk.dataset.completar);
      f.append("entrega_id", chk.dataset.entrega || "");
      f.append("completada", chk.checked ? "1" : "0");
      fetch(chk.dataset.url, { method: "POST", body: f })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var det = chk.closest("details");
          if (det) det.setAttribute("data-hecha", chk.checked ? "1" : "0");
          if (d.ok && typeof d.progreso === "number") {
            var b = document.querySelector("[data-progreso]");
            if (b) {
              b.querySelector("i").style.width = d.progreso + "%";
              var et = document.querySelector("[data-progreso-texto]");
              if (et) et.textContent = d.progreso + "%";
            }
          }
        })
        .catch(function () {});
    });
  });

  /* ---------- Medidor de fortaleza de contraseña ---------- */
  document.querySelectorAll("[data-fuerza]").forEach(function (inp) {
    var salida = document.querySelector(inp.getAttribute("data-fuerza"));
    if (!salida) return;
    inp.addEventListener("input", function () {
      var v = inp.value, p = 0;
      if (v.length >= 8) p++;
      if (v.length >= 12) p++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) p++;
      if (/\d/.test(v)) p++;
      if (/[^A-Za-z0-9]/.test(v)) p++;
      var textos = ["Muy débil", "Débil", "Aceptable", "Buena", "Fuerte", "Excelente"];
      salida.textContent = v ? "Seguridad: " + textos[p] : "";
    });
  });

  /* ---------- Selección múltiple en tablas ---------- */
  document.querySelectorAll("[data-marcar-todo]").forEach(function (master) {
    master.addEventListener("change", function () {
      document
        .querySelectorAll(master.getAttribute("data-marcar-todo"))
        .forEach(function (c) { if (!c.disabled) c.checked = master.checked; });
    });
  });

  /* ---------- Aviso antes de salir con cambios sin guardar ---------- */
  document.querySelectorAll("form[data-avisar]").forEach(function (form) {
    var sucio = false;
    form.addEventListener("input", function () { sucio = true; });
    form.addEventListener("submit", function () { sucio = false; });
    window.addEventListener("beforeunload", function (e) {
      if (sucio) { e.preventDefault(); e.returnValue = ""; }
    });
  });
})();
