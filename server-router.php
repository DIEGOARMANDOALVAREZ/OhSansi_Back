<?php
// server-router.php (raíz del proyecto)
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$requested = __DIR__ . '/public' . $uri;

if ($uri !== '/' && file_exists($requested) && !is_dir($requested)) {
    return false; // sirve archivos estáticos reales
}

require_once __DIR__ . '/public/index.php';
