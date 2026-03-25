<?php

$GLOBALS['_FW_PAGE_STARTED'] = false;
$GLOBALS['_FW_PAGE_TITLE'] = '';
$GLOBALS['_FW_PAGE_TEMPLATE'] = '';
$GLOBALS['_FW_PAGE_TEMPLATE_DATA'] = [];

function pageStart(string $title = '', ?string $template = null, ?array $templateData = null): void
{
    if (!empty($GLOBALS['_FW_PAGE_STARTED'])) {
        return;
    }

    $GLOBALS['_FW_PAGE_STARTED'] = true;
    $GLOBALS['_FW_PAGE_TITLE'] = $title;
    $GLOBALS['_FW_PAGE_TEMPLATE'] = $template ?? '';
    $GLOBALS['_FW_PAGE_TEMPLATE_DATA'] = $templateData ?? [];

    ob_start();
}

function pageEnd(array $pageData = []): void
{
    $content = ob_get_clean();
    if ($content === false) {
        $content = '';
    }

    $title = $GLOBALS['_FW_PAGE_TITLE'];
    if ($title === '') {
        $title = fwConfig('title', 'CookedFramework');
    }

    $template = trim((string) $GLOBALS['_FW_PAGE_TEMPLATE']);
    if ($template === '') {
        $template = fwConfig('default_template', 'default');
    }

    $templateData = $GLOBALS['_FW_PAGE_TEMPLATE_DATA'] ?? [];
    if (!is_array($templateData)) {
        $templateData = [];
    }

    $layoutFile = fwLayoutPath($template);

    if ($layoutFile === null) {
        $fallback = fwConfig('default_template', 'default');
        $layoutFile = fwLayoutPath($fallback);
    }

    if ($layoutFile === null) {
        throw new RuntimeException('Nie znaleziono layoutu.');
    }

    $pageDataJson = json_encode($pageData, JSON_UNESCAPED_UNICODE);
    $assetsBase = fwUrl('public/assets');

    require FW . 'shell.php';
    exit;
}

function component(string $name, array $data = []): void
{
    $file = fwComponentPath($name);
    if ($file === null) {
        return;
    }

    extract($data, EXTR_SKIP);
    require $file;
}

function fwViewName(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $name = trim($name, '/');

    return preg_replace('#[^a-zA-Z0-9_/\-]#', '', $name);
}

function fwLayoutPath(string $name): ?string
{
    $name = fwViewName($name);
    if ($name === '') {
        return null;
    }

    $direct = APP . 'layouts/' . $name . '.php';
    $index  = APP . 'layouts/' . $name . '/index.php';

    if (is_file($direct)) {
        return $direct;
    }

    if (is_file($index)) {
        return $index;
    }

    return null;
}

function fwComponentPath(string $name): ?string
{
    $name = fwViewName($name);
    if ($name === '') {
        return null;
    }

    $direct = APP . 'components/' . $name . '.php';
    $index  = APP . 'components/' . $name . '/index.php';

    if (is_file($direct)) {
        return $direct;
    }

    if (is_file($index)) {
        return $index;
    }

    return null;
}