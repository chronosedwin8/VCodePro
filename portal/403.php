<?php
/**
 * Acceso denegado. Se incluye desde exigir_rol().
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

cabecera('Sin permiso', [
    'titulo' => 'No tienes permiso para ver esta página',
    'sub'    => 'Tu cuenta no incluye este módulo del portal. Si crees que es un error, escribe a la coordinación de Tecnología.',
]);
?>
<div class="panel">
  <p class="txt-muted">Estás dentro del portal como <strong><?= h(ROLES[rol()] ?? '') ?></strong>.</p>
  <div class="form-acc">
    <a class="btn" href="<?= url(panel_de(rol())) ?>">Ir a mi panel</a>
    <a class="btn btn-ghost" href="<?= url('portal/logout.php') ?>">Cerrar sesión</a>
  </div>
</div>
<?php pie(); ?>
