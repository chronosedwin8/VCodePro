<?php
/**
 * Plantilla del portal: barra superior, menú lateral por rol y pie.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/richtext.php';

/** Menú lateral según el rol. Cada entrada: [archivo, etiqueta, icono, ruta]. */
function menu_lateral(string $rol): array {
    $m = [
        'admin' => [
            ['Gestión', [
                ['index.php',        'Panel general',  'grid',    'portal/admin/index.php'],
                ['usuarios.php',     'Usuarios',       'users',   'portal/admin/usuarios.php'],
                ['colegios.php',     'Colegios',       'school',  'portal/admin/colegios.php'],
                ['grupos.php',       'Grupos',         'layers',  'portal/admin/grupos.php'],
            ]],
            ['Currículo', [
                ['niveles.php',      'Niveles IB',     'stairs',  'portal/admin/niveles.php'],
                ['actividades.php',  'Actividades',    'book',    'portal/admin/actividades.php'],
                ['insignias.php',    'Insignias',      'badge',   'portal/admin/insignias.php'],
            ]],
            ['Comercial', [
                ['licencias.php',    'Licencias',      'key',     'portal/admin/licencias.php'],
                ['facturas.php',     'Facturación',    'receipt', 'portal/admin/facturas.php'],
                ['tickets.php',      'Soporte',        'life',    'portal/admin/tickets.php'],
                ['mensajes.php',     'Mensajes web',   'mail',    'portal/admin/mensajes.php'],
            ]],
            ['Sistema', [
                ['informes.php',     'Informes',       'chart',   'portal/admin/informes.php'],
                ['auditoria.php',    'Auditoría',      'shield',  'portal/admin/auditoria.php'],
                ['ajustes.php',      'Ajustes',        'gear',    'portal/admin/ajustes.php'],
            ]],
        ],
        'docente' => [
            ['Aula', [
                ['index.php',        'Panel',          'grid',    'portal/docente/index.php'],
                ['grupos.php',       'Mis grupos',     'layers',  'portal/docente/grupos.php'],
                ['estudiantes.php',  'Estudiantes',    'users',   'portal/docente/estudiantes.php'],
                ['importar.php',     'Importar de Phidias', 'down', 'portal/docente/importar.php'],
                ['seguimiento.php',  'Seguimiento',    'chart',   'portal/docente/seguimiento.php'],
            ]],
            ['Trabajo', [
                ['banco.php',        'Banco de actividades', 'book', 'portal/docente/banco.php'],
                ['asignaciones.php', 'Asignaciones',   'clipboard', 'portal/docente/asignaciones.php'],
                ['calificar.php',    'Por calificar',  'check',   'portal/docente/calificar.php'],
                ['informes.php',     'Informes',       'receipt', 'portal/docente/informes.php'],
            ]],
        ],
        'estudiante' => [
            ['Mi trabajo', [
                ['index.php',        'Panel',          'grid',    'portal/estudiante/index.php'],
                ['actividades.php',  'Mis actividades','book',    'portal/estudiante/actividades.php'],
                ['bitacora.php',     'Bitácora',       'pen',     'portal/estudiante/bitacora.php'],
                ['calificaciones.php','Calificaciones','check',   'portal/estudiante/calificaciones.php'],
            ]],
            ['Progreso', [
                ['portafolio.php',   'Portafolio',     'folder',  'portal/estudiante/portafolio.php'],
                ['insignias.php',    'Insignias',      'badge',   'portal/estudiante/insignias.php'],
                ['grupos.php',       'Mis grupos',     'layers',  'portal/estudiante/grupos.php'],
            ]],
        ],
        'cliente' => [
            ['Mi cuenta', [
                ['index.php',        'Panel',          'grid',    'portal/cliente/index.php'],
                ['licencias.php',    'Licencias',      'key',     'portal/cliente/licencias.php'],
                ['puestos.php',      'Puestos',        'users',   'portal/cliente/puestos.php'],
            ]],
            ['Servicio', [
                ['facturas.php',     'Facturas',       'receipt', 'portal/cliente/facturas.php'],
                ['soporte.php',      'Soporte',        'life',    'portal/cliente/soporte.php'],
                ['descargas.php',    'Descargas',      'down',    'portal/cliente/descargas.php'],
            ]],
        ],
    ];
    return $m[$rol] ?? [];
}

function icono(string $n): string {
    $p = [
        'grid'      => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'school'    => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.7 3 6 3s6-2 6-3v-5"/>',
        'layers'    => '<path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
        'stairs'    => '<path d="M3 21h4v-4h4v-4h4V9h4V5h4"/>',
        'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'badge'     => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
        'key'       => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9 3 3-3 3-2-2-2 2-2-2"/>',
        'receipt'   => '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2z"/><path d="M8 9h8M8 13h6"/>',
        'life'      => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="m4.9 4.9 4.2 4.2m5.8 5.8 4.2 4.2m0-14.2-4.2 4.2M9.1 14.9l-4.2 4.2"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="m7 15 4-5 3 3 5-7"/>',
        'shield'    => '<path d="M12 2 4 6v6c0 5 3.4 9 8 10 4.6-1 8-5 8-10V6l-8-4z"/>',
        'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 3V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 10h.1a2 2 0 1 1 0 4H21c-.7 0-1.3.4-1.6 1z"/>',
        'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
        'check'     => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'pen'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
        'folder'    => '<path d="M4 20a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2z"/>',
        'down'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'bell'      => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'out'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? $p['grid']) . '</svg>';
}

/**
 * Cabecera del portal.
 * $op: ['titulo' => string, 'migas' => [[texto, url]], 'acciones' => html, 'ancho' => 'full'|'normal']
 */
function cabecera(string $titulo, array $op = []): void {
    $u = usuario();
    $rol = $u['rol'] ?? '';
    $noSinSesion = $op['publica'] ?? false;
    $tema = $u['tema'] ?? 'dark';
    $sinLeer = $u ? (int) valor('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0', [$u['id']], 0) : 0;
    $actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?><!DOCTYPE html>
<html lang="es" data-theme="<?= h($tema) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · <?= APP_NOMBRE ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="alternate icon" href="<?= url('assets/img/favicon.ico') ?>" sizes="any">
<link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/portal.css') ?>">
<script>
  (function () {
    try {
      var t = localStorage.getItem("vcodepro-tema");
      if (t) document.documentElement.setAttribute("data-theme", t);
    } catch (e) {}
  })();
</script>
</head>
<body class="portal<?= $noSinSesion ? ' portal-publico' : '' ?>" data-api="<?= url('portal/api') ?>">
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>
<?php if (!$noSinSesion && $u): ?>

<header class="pt-top">
  <button class="pt-burger" type="button" id="pt-burger" aria-label="Abrir menú" aria-expanded="false"><span></span></button>
  <a class="pt-brand" href="<?= url(panel_de($rol)) ?>">
    <img src="<?= url('assets/img/logo-mark.svg') ?>" alt="" width="26" height="26">
    <span>vcode<span class="pro">pro</span></span>
    <em><?= h(ROLES[$rol] ?? '') ?></em>
  </a>

  <div class="pt-actions">
    <a class="pt-icon" href="<?= url('portal/notificaciones.php') ?>" aria-label="Notificaciones">
      <?= icono('bell') ?><?php if ($sinLeer): ?><i class="pt-dot"><?= $sinLeer > 9 ? '9+' : $sinLeer ?></i><?php endif; ?>
    </a>
    <button class="pt-icon theme-toggle" type="button" aria-label="Cambiar tema" aria-pressed="false">
      <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    </button>
    <div class="pt-user" data-menu>
      <button type="button" class="pt-avatar" aria-haspopup="true" aria-expanded="false">
        <span><?= h(iniciales($u['nombre'], $u['apellidos'])) ?></span>
      </button>
      <div class="pt-drop" hidden>
        <p class="pt-drop-h"><strong><?= h(nombre_completo($u)) ?></strong><br><span><?= h($u['email']) ?></span></p>
        <a href="<?= url('portal/perfil.php') ?>">Mi perfil</a>
        <a href="<?= url('portal/notificaciones.php') ?>">Notificaciones<?= $sinLeer ? ' (' . $sinLeer . ')' : '' ?></a>
        <a href="<?= url('index.html') ?>">Ir al sitio público</a>
        <a class="pt-salir" href="<?= url('portal/logout.php') ?>"><?= icono('out') ?> Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>

<div class="pt-shell">
  <nav class="pt-side" id="pt-side" aria-label="Navegación del portal">
    <?php foreach (menu_lateral($rol) as [$grupo, $items]): ?>
      <p class="pt-side-h"><?= h($grupo) ?></p>
      <ul>
        <?php foreach ($items as [$archivo, $etiqueta, $ic, $ruta]): ?>
          <li><a href="<?= url($ruta) ?>"<?= $actual === $archivo ? ' class="on" aria-current="page"' : '' ?>><?= icono($ic) ?><span><?= h($etiqueta) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
    <?php if ($u['colegio_nombre'] ?? null): ?>
      <p class="pt-side-col"><?= h($u['colegio_nombre']) ?></p>
    <?php endif; ?>
  </nav>

  <main class="pt-main<?= ($op['ancho'] ?? '') === 'full' ? ' pt-full' : '' ?>" id="contenido">
    <div class="pt-head">
      <div>
        <?php if (!empty($op['migas'])): ?>
          <nav class="pt-migas" aria-label="Ruta"><ol>
            <?php foreach ($op['migas'] as $i => $m): ?>
              <li><?php if (!empty($m[1]) && $i < count($op['migas']) - 1): ?><a href="<?= url($m[1]) ?>"><?= h($m[0]) ?></a><?php else: ?><?= h($m[0]) ?><?php endif; ?></li>
            <?php endforeach; ?>
          </ol></nav>
        <?php endif; ?>
        <h1><?= h($op['titulo'] ?? $titulo) ?></h1>
        <?php if (!empty($op['sub'])): ?><p class="pt-sub"><?= h($op['sub']) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($op['acciones'])): ?><div class="pt-head-acc"><?= $op['acciones'] ?></div><?php endif; ?>
    </div>
    <?= pintar_flash() ?>
<?php else: ?>
  <main class="pt-auth" id="contenido">
<?php endif; ?>
<?php
}

function pie(array $op = []): void {
    $u = usuario();
    $publica = $op['publica'] ?? false;
    ?>
  </main>
<?php if (!$publica && $u): ?>
</div>
<footer class="pt-foot">
  <span>&copy; <?= date('Y') ?> <?= APP_NOMBRE ?> · <?= APP_LEMA ?></span>
  <span><a href="<?= url('privacidad.html') ?>">Privacidad</a> · <a href="<?= url('documentacion.html') ?>">Documentación</a></span>
</footer>
<?php endif; ?>
<script src="<?= url('assets/js/portal.js') ?>"></script>
<script src="<?= url('assets/js/editor.js') ?>"></script>
<script src="<?= url('assets/js/adjuntos.js') ?>"></script>
<script src="<?= url('assets/js/ia.js') ?>"></script>
</body>
</html>
<?php
}

/** Tarjeta de métrica reutilizable. */
function metrica(string $etiqueta, $valor, ?string $pie = null, string $tono = ''): string {
    return '<div class="kpi' . ($tono ? ' kpi-' . $tono : '') . '">'
         . '<b>' . h((string) $valor) . '</b>'
         . '<span>' . h($etiqueta) . '</span>'
         . ($pie ? '<em>' . h($pie) . '</em>' : '')
         . '</div>';
}

/** Barra de progreso. */
function barra(int $pct, string $tono = ''): string {
    $pct = max(0, min(100, $pct));
    return '<div class="barra' . ($tono ? ' barra-' . $tono : '') . '" role="progressbar" aria-valuenow="' . $pct
         . '" aria-valuemin="0" aria-valuemax="100"><i style="width:' . $pct . '%"></i></div>';
}

/** Estado vacío. */
function vacio(string $titulo, string $texto = '', string $accionHtml = ''): string {
    return '<div class="vacio"><h3>' . h($titulo) . '</h3>' . ($texto ? '<p>' . h($texto) . '</p>' : '') . $accionHtml . '</div>';
}
