<?php

define('FW', __DIR__ . '/framework/');
require FW . 'framework.php';

if (isset($_GET['__not_found'])) {
    $urlStart = __DIR__ . '/app/pages/';

    http_response_code(404);

    if (is_file($urlStart . '404.php')) {
        require $urlStart . '404.php';
        exit;
    }

    if (is_file($urlStart . '404.html')) {
        require $urlStart . '404.html';
        exit;
    }

    echo '404 - Nie znaleziono strony';
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';


$baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');


if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($uri, $baseDir)) {
    $uri = substr($uri, strlen($baseDir));
}

$uri = trim($uri, '/');


if ($uri === '') {
    $uri = 'index';
}


$uri = preg_replace('/[^a-zA-Z0-9_\\/-]/', '', $uri);


$urlStart = __DIR__ . '/app/pages/';
$directFile =  $urlStart . $uri . '.php';
$indexFile  = $urlStart . $uri . '/index.php';

if (is_file($directFile)) {
    require $directFile;
    exit;
}

if (is_file($indexFile)) {
    require $indexFile;
    exit;
}

http_response_code(404);

if (is_file($urlStart . "404.php")) {
    require $urlStart . "404.php";
    exit;
}

if (is_file($urlStart . "404.html")) {
    require $urlStart . "404.html";
    exit;
}

echo '404 - Nie znaleziono strony';