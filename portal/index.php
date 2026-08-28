<?php
/**
 * Puerta de entrada al portal: lleva a cada rol a su panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$u = usuario();
redirigir($u ? panel_de($u['rol']) : 'portal/login.php');
