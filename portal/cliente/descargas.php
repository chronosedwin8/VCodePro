<?php
/**
 * Descargas del editor para el cliente, con su clave de licencia a la vista.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/academico.php';

$u = exigir_rol('cliente', 'admin');

$licencia = fila('SELECT * FROM licencias
                   WHERE (cliente_id = ? OR colegio_id = ?) AND estado = "activa"
                ORDER BY vence_en DESC LIMIT 1', [$u['id'], $u['colegio_id']]);

/** Catálogo de descargas: mismos destinos que el sitio público. */
$plataformas = [
    'Windows' => [
        ['Instalador de usuario · x64', 'win32-x64-user'],
        ['Instalador del sistema · x64', 'win32-x64'],
        ['Paquete ZIP · x64', 'win32-x64-archive'],
        ['Instalador de usuario · ARM64', 'win32-arm64-user'],
    ],
    'macOS' => [
        ['Apple Silicon', 'darwin-arm64'],
        ['Intel', 'darwin'],
        ['Universal', 'darwin-universal'],
    ],
    'Linux' => [
        ['Debian y Ubuntu · .deb x64', 'linux-deb-x64'],
        ['Red Hat y Fedora · .rpm x64', 'linux-rpm-x64'],
        ['Paquete .tar.gz · x64', 'linux-x64'],
        ['Debian y Ubuntu · .deb ARM64', 'linux-deb-arm64'],
    ],
];

cabecera('Descargas', [
    'titulo' => 'Descargas e implantación',
    'sub'    => 'Instaladores del editor y guía para el despliegue en salas de cómputo.',
    'migas'  => [['Panel', 'portal/cliente/index.php'], ['Descargas']],
]);
?>
<?php if ($licencia): ?>
<div class="panel">
  <div class="panel-h">
    <div>
      <h2>Tu clave de activación</h2>
      <p>Se introduce una vez por equipo, en el primer arranque del editor.</p>
    </div>
    <code class="mono copiar" data-copiar="<?= h($licencia['clave']) ?>"
          style="font-size:1.25rem;letter-spacing:.16em;padding:.5rem .9rem;border:1px solid var(--border);border-radius:10px;background:var(--bg-alt)"><?= h($licencia['clave']) ?></code>
  </div>
</div>
<?php else: ?>
<div class="aviso aviso-warn"><div>No hay una licencia activa vinculada a tu cuenta. Puedes instalar el editor, pero necesitarás una clave para activarlo.</div></div>
<?php endif; ?>

<div class="rejilla rej-lat">
  <div>
    <?php foreach ($plataformas as $nombre => $items): ?>
      <div class="panel panel-plano">
        <div class="panel-h"><h2><?= h($nombre) ?></h2></div>
        <div class="tabla-caja">
          <table class="tabla">
            <tbody>
            <?php foreach ($items as [$etiqueta, $slug]): ?>
              <tr>
                <td><strong><?= h($etiqueta) ?></strong></td>
                <td class="acc">
                  <a class="btn btn-xs" rel="noopener"
                     href="https://update.code.visualstudio.com/latest/<?= h($slug) ?>/stable">Descargar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <aside>
    <div class="panel">
      <div class="panel-h"><h3>Despliegue en sala de cómputo</h3></div>
      <ol class="txt-sm" style="padding-left:1.1rem">
        <li>Descarga el instalador del sistema (no el de usuario).</li>
        <li>Instálalo con permisos de administrador en el equipo modelo.</li>
        <li>Activa la licencia con la clave de arriba.</li>
        <li>Comprueba que la carpeta de trabajo del estudiante sea local, no de red.</li>
        <li>Clona la imagen o repite la instalación desatendida en los demás equipos.</li>
        <li>Registra cada equipo en <a href="<?= url('portal/cliente/puestos.php') ?>">el reparto de puestos</a>.</li>
      </ol>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>Requisitos mínimos</h3></div>
      <dl class="dl">
        <dt>Procesador</dt><dd>x64 o ARM64, doble núcleo</dd>
        <dt>Memoria</dt><dd>4 GB (8 GB recomendados)</dd>
        <dt>Disco</dt><dd>1,5 GB libres</dd>
        <dt>Red</dt><dd>Salida a internet para la IA y la activación</dd>
      </dl>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= url('descargas.html') ?>">Ver el catálogo completo</a>
    </div>

    <div class="panel">
      <div class="panel-h"><h3>¿Problemas al instalar?</h3></div>
      <p class="txt-sm mb-0">Abre un ticket con la categoría técnica e indica el sistema operativo y el mensaje de error exacto.</p>
      <a class="btn btn-sm mt-2" href="<?= url('portal/cliente/soporte.php?asunto=' . urlencode('Problema de instalación')) ?>">Abrir un ticket</a>
    </div>
  </aside>
</div>
<?php pie(); ?>
