/* ==========================================================================
   Notas de voz
   --------------------------------------------------------------------------
   Graba una nota de voz de hasta cinco minutos y la sube como adjunto de la
   entrega, que termina en S3 igual que cualquier otro archivo.

   La optimización ocurre aquí, antes de subir, porque es donde sale gratis:
   el navegador graba directamente en mono, con supresión de ruido y en Opus a
   24 kbps. Para voz eso suena bien y pesa unos 180 KB por minuto, frente a los
   ~5,7 MB por minuto del audio sin comprimir: una nota de cinco minutos queda
   por debajo de 1 MB, que sube sin problema con la wifi de un colegio.
   Safari no graba Opus; ahí se usa AAC, algo más pesado.

   Antes de guardar, el estudiante escucha lo que grabó y decide si la envía o
   la repite. Si la subida falla, la grabación no se pierde: se puede reintentar.
   ========================================================================== */
(function () {
  "use strict";

  var soportado = !!(window.isSecureContext && navigator.mediaDevices &&
                     navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  var grabando = null;          // una sola grabación por página
  var sinGuardar = 0;           // grabaciones hechas y no guardadas

  window.addEventListener("beforeunload", function (e) {
    if (sinGuardar > 0) { e.preventDefault(); e.returnValue = ""; }
  });

  /** Formato que el navegador sabe grabar, del más ligero al más compatible. */
  function formato() {
    var opciones = [
      { mime: "audio/webm;codecs=opus",     tasa: 24000 },
      { mime: "audio/ogg;codecs=opus",      tasa: 24000 },
      { mime: "audio/mp4;codecs=mp4a.40.2", tasa: 48000 },   // Safari
      { mime: "audio/mp4",                  tasa: 48000 }
    ];
    for (var i = 0; i < opciones.length; i++) {
      if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(opciones[i].mime)) return opciones[i];
    }
    return { mime: "", tasa: 32000 };
  }

  function mmss(seg) {
    seg = Math.max(0, Math.floor(seg));
    return Math.floor(seg / 60) + ":" + ("0" + (seg % 60)).slice(-2);
  }
  function peso(bytes) {
    return bytes < 1048576
      ? Math.max(1, Math.round(bytes / 1024)) + " KB"
      : (bytes / 1048576).toFixed(1).replace(".", ",") + " MB";
  }

  var ICONO_MIC = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">'
    + '<path fill="currentColor" d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/></svg>';

  function montar(caja) {
    if (caja.dataset.vozListo) return;
    caja.dataset.vozListo = "1";

    var adj = caja.closest("[data-adj]");
    if (!adj) return;
    var maxSeg   = parseInt(caja.dataset.max, 10) || 300;
    var maxBytes = parseInt(caja.dataset.maxBytes, 10) || 8388608;

    if (!soportado) {
      caja.innerHTML = '<span class="voz-dato">' + (window.isSecureContext
        ? "Este navegador no permite grabar notas de voz. Prueba con Chrome, Edge o Safari."
        : "Para grabar notas de voz, la página debe abrirse con https.") + "</span>";
      return;
    }

    var s = { stream: null, rec: null, trozos: [], mime: "", inicio: 0, seg: 0,
              reloj: null, anim: null, ctx: null, blob: null, url: "",
              descartar: false, pendiente: false };

    function marcarPendiente(si) {
      if (si && !s.pendiente) { s.pendiente = true; sinGuardar++; }
      if (!si && s.pendiente) { s.pendiente = false; sinGuardar--; }
    }

    function avisar(texto, clase) {
      var el = adj.querySelector("[data-adj-estado]");
      if (!el) return;
      el.textContent = texto;
      el.className = "adj-estado" + (clase ? " " + clase : "");
    }

    // ---------------------------------------------------------- reposo ----
    function pintarReposo() {
      caja.classList.remove("voz-grabando");
      caja.innerHTML = '<button type="button" class="btn btn-sm btn-ghost voz-boton">' + ICONO_MIC
        + '<span>Grabar nota de voz</span></button>'
        + '<span class="voz-dato">Hasta ' + Math.round(maxSeg / 60) + ' minutos</span>';
      caja.querySelector("button").addEventListener("click", empezar);
    }

    // -------------------------------------------------------- grabación ---
    function empezar() {
      if (grabando && grabando !== caja) {
        avisar("Ya hay otra nota de voz grabándose en esta página. Termínala primero.", "es-error");
        return;
      }
      caja.innerHTML = '<span class="voz-dato">Pidiendo permiso para usar el micrófono…</span>';

      navigator.mediaDevices.getUserMedia({ audio: {
        channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true
      }}).then(function (stream) {
        s.stream = stream;
        var f = formato();
        var opciones = { audioBitsPerSecond: f.tasa };
        if (f.mime) opciones.mimeType = f.mime;
        var rec;
        try { rec = new MediaRecorder(stream, opciones); }
        catch (e) { rec = new MediaRecorder(stream); }

        s.rec = rec;
        s.trozos = [];
        s.mime = rec.mimeType || f.mime || "audio/webm";
        s.descartar = false;
        rec.ondataavailable = function (ev) { if (ev.data && ev.data.size) s.trozos.push(ev.data); };
        rec.onstop = alTerminar;
        rec.start(1000);                 // un trozo por segundo: nada se pierde si algo falla
        s.inicio = Date.now();
        grabando = caja;
        marcarPendiente(true);

        pintarGrabando();
        medirNivel(stream);
        s.reloj = setInterval(tic, 250);
      }).catch(function (err) {
        var nombre = err && err.name;
        pintarError(nombre === "NotAllowedError"
          ? "No diste permiso para usar el micrófono. Actívalo desde el candado de la barra de direcciones."
          : nombre === "NotFoundError"
            ? "No se encontró ningún micrófono conectado."
            : "No se pudo acceder al micrófono.", false);
      });
    }

    function pintarGrabando() {
      caja.classList.add("voz-grabando");
      caja.innerHTML = '<span class="voz-punto" aria-hidden="true"></span>'
        + '<span class="voz-tiempo" data-t role="timer">0:00</span>'
        + '<span class="voz-dato">de ' + mmss(maxSeg) + '</span>'
        + '<span class="voz-nivel" aria-hidden="true"><i data-nivel></i></span>'
        + '<button type="button" class="btn btn-sm" data-parar>Detener</button>'
        + '<button type="button" class="btn btn-sm btn-ghost" data-descartar>Descartar</button>';
      caja.querySelector("[data-parar]").addEventListener("click", detener);
      caja.querySelector("[data-descartar]").addEventListener("click", function () {
        s.descartar = true;
        detener();
      });
    }

    function tic() {
      var seg = (Date.now() - s.inicio) / 1000;
      var t = caja.querySelector("[data-t]");
      if (t) {
        t.textContent = mmss(seg);
        t.classList.toggle("es-final", seg >= maxSeg - 15);
      }
      if (seg >= maxSeg) detener();      // tope duro: cinco minutos
    }

    function detener() {
      clearInterval(s.reloj);
      s.seg = Math.min(maxSeg, (Date.now() - s.inicio) / 1000);
      if (s.rec && s.rec.state !== "inactive") s.rec.stop();
      else alTerminar();
      liberar();
    }

    /** Suelta el micrófono: se apaga el indicador del navegador. */
    function liberar() {
      if (s.anim) cancelAnimationFrame(s.anim);
      s.anim = null;
      if (s.ctx) { try { s.ctx.close(); } catch (e) {} s.ctx = null; }
      if (s.stream) { s.stream.getTracks().forEach(function (t) { t.stop(); }); s.stream = null; }
    }

    function alTerminar() {
      grabando = null;
      if (s.descartar) {
        s.trozos = [];
        marcarPendiente(false);
        pintarReposo();
        return;
      }
      var tipo = (s.mime.split(";")[0]) || "audio/webm";
      var blob = new Blob(s.trozos, { type: tipo });
      s.trozos = [];
      if (s.seg < 1 || blob.size < 1000) {
        marcarPendiente(false);
        pintarError("La grabación quedó vacía. Revisa el micrófono e inténtalo de nuevo.", false);
        return;
      }
      if (blob.size > maxBytes) {
        marcarPendiente(false);
        pintarError("La nota pesa " + peso(blob.size) + " y el máximo es " + peso(maxBytes) + ".", false);
        return;
      }
      s.blob = blob;
      pintarRevision();
    }

    /** Barra de nivel: le confirma al estudiante que el micrófono le oye. */
    function medirNivel(stream) {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      try {
        var ctx = new Ctx();
        s.ctx = ctx;
        var analizador = ctx.createAnalyser();
        analizador.fftSize = 512;
        ctx.createMediaStreamSource(stream).connect(analizador);
        var datos = new Uint8Array(analizador.fftSize);
        (function pintar() {
          if (!s.ctx) return;
          analizador.getByteTimeDomainData(datos);
          var pico = 0;
          for (var i = 0; i < datos.length; i++) {
            var v = Math.abs(datos[i] - 128);
            if (v > pico) pico = v;
          }
          var barra = caja.querySelector("[data-nivel]");
          if (barra) barra.style.width = Math.min(100, (pico / 128) * 180) + "%";
          s.anim = requestAnimationFrame(pintar);
        })();
      } catch (e) { /* sin medidor no pasa nada */ }
    }

    // --------------------------------------------------------- revisión ---
    function pintarRevision() {
      if (s.url) URL.revokeObjectURL(s.url);
      s.url = URL.createObjectURL(s.blob);
      caja.classList.remove("voz-grabando");
      caja.innerHTML = '<div class="voz-previa">'
        + '<audio controls preload="metadata"></audio>'
        + '<span class="voz-dato">' + mmss(s.seg) + ' · ' + peso(s.blob.size) + '</span>'
        + '<button type="button" class="btn btn-sm" data-guardar>Guardar nota de voz</button>'
        + '<button type="button" class="btn btn-sm btn-ghost" data-rehacer>Volver a grabar</button>'
        + '<button type="button" class="btn btn-sm btn-ghost" data-tirar>Descartar</button>'
        + '</div>';
      caja.querySelector("audio").src = s.url;
      caja.querySelector("[data-guardar]").addEventListener("click", subir);
      caja.querySelector("[data-rehacer]").addEventListener("click", function () {
        olvidar();
        empezar();
      });
      caja.querySelector("[data-tirar]").addEventListener("click", function () {
        olvidar();
        pintarReposo();
      });
    }

    function olvidar() {
      if (s.url) URL.revokeObjectURL(s.url);
      s.url = "";
      s.blob = null;
      marcarPendiente(false);
    }

    // ----------------------------------------------------------- subida ---
    function subir() {
      if (!s.blob) return;
      var tipo = s.blob.type;
      var ext = /ogg/.test(tipo) ? "ogg" : /mp4|aac|m4a/.test(tipo) ? "m4a" : "webm";

      var f = new FormData();
      f.append("_csrf", adj.dataset.csrf || "");
      f.append("entrega_id", adj.dataset.entrega || "");
      f.append("fase_id", adj.dataset.fase || "");
      f.append("duracion", String(Math.round(s.seg)));
      f.append("audio", s.blob, "nota-de-voz." + ext);

      caja.innerHTML = '<span class="voz-dato" data-msg>Guardando nota de voz…</span>'
        + '<span class="voz-barra" aria-hidden="true"><i data-avance></i></span>';
      var avance = caja.querySelector("[data-avance]");

      // XMLHttpRequest y no fetch: es lo único que informa del avance de subida.
      var xhr = new XMLHttpRequest();
      xhr.open("POST", caja.dataset.url);
      xhr.setRequestHeader("X-Requested-With", "fetch");
      xhr.upload.onprogress = function (ev) {
        if (ev.lengthComputable && avance) avance.style.width = (ev.loaded / ev.total) * 100 + "%";
      };
      xhr.onload = function () {
        var d;
        try { d = JSON.parse(xhr.responseText); }
        catch (e) { d = { ok: false, error: "Respuesta inesperada del servidor (" + xhr.status + ")." }; }
        if (d.ok && d.adjunto) {
          olvidar();
          pintarEnLista(d.adjunto);
          pintarReposo();
          avisar("Nota de voz guardada.", "es-ok");
        } else {
          pintarError(d.error || "No se pudo guardar la nota de voz.", true);
        }
      };
      xhr.onerror = function () { pintarError("Sin conexión con el servidor.", true); };
      xhr.send(f);
    }

    function pintarError(msg, conGrabacion) {
      caja.classList.remove("voz-grabando");
      caja.innerHTML = '<span class="voz-dato es-error">' + msg.replace(/</g, "&lt;") + '</span>'
        + (conGrabacion && s.blob
            ? '<button type="button" class="btn btn-sm" data-reintentar>Reintentar</button>'
              + '<button type="button" class="btn btn-sm btn-ghost" data-oir>Escucharla otra vez</button>'
            : '<button type="button" class="btn btn-sm btn-ghost" data-volver>Entendido</button>');
      var r = caja.querySelector("[data-reintentar]");
      if (r) r.addEventListener("click", subir);
      var o = caja.querySelector("[data-oir]");
      if (o) o.addEventListener("click", pintarRevision);
      var v = caja.querySelector("[data-volver]");
      if (v) v.addEventListener("click", pintarReposo);
    }

    /** La nota guardada entra en la lista de adjuntos con su reproductor. */
    function pintarEnLista(a) {
      var lista = adj.querySelector("[data-adj-lista]");
      if (!lista) return;
      var li = document.createElement("li");
      li.className = "es-audio";
      li.dataset.id = a.id;

      var tipo = document.createElement("span");
      tipo.className = "adj-tipo adj-tipo-voz";
      tipo.textContent = "VOZ";
      var audio = document.createElement("audio");
      audio.controls = true;
      audio.preload = "none";
      audio.src = a.reproducir;
      var dur = document.createElement("span");
      dur.className = "adj-duracion";
      dur.textContent = a.duracion || "";
      var pesoEl = document.createElement("span");
      pesoEl.className = "adj-peso";
      pesoEl.textContent = a.peso;

      li.appendChild(tipo); li.appendChild(audio); li.appendChild(dur); li.appendChild(pesoEl);
      if (a.borrable) {
        // El borrado lo gestiona adjuntos.js, que escucha los clics de la lista.
        var b = document.createElement("button");
        b.type = "button";
        b.className = "adj-quitar";
        b.dataset.quitar = a.id;
        b.title = "Quitar";
        b.setAttribute("aria-label", "Quitar la nota de voz");
        b.innerHTML = "&times;";
        li.appendChild(b);
      }
      lista.appendChild(li);
    }

    pintarReposo();
  }

  function iniciar() {
    document.querySelectorAll("[data-voz]").forEach(montar);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
