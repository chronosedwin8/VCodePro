<?php
/**
 * Precios vigentes, en JSON, para las páginas estáticas del sitio.
 *
 * La portada y la página de precios son HTML sin PHP; assets/js/planes.js
 * pide esto al cargar y pinta los valores que la administración fijó en
 * Admin → Precios. Son los mismos que se cobran en Mercado Pago, porque ambos
 * salen de plan_catalogo().
 *
 * Público y sin sesión: no expone nada que no esté ya en la web.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/planes.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// Cinco minutos: un cambio de precio llega pronto a la web sin consultar la
// base de datos en cada visita a la portada.
header('Cache-Control: public, max-age=300');

echo json_encode([
    'moneda' => 'COP',
    'planes' => planes_publicos(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
