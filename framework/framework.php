<?php

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__) . '/');
}

if (!defined('FW')) {
    define('FW', ROOT . 'framework/');
}

if (!defined('APP')) {
    define('APP', ROOT . 'app/');
}

require_once FW . 'api.php';
require_once FW . 'db.php';
require_once FW . 'page.php';

$GLOBALS['_FW_CONFIG'] = require APP . 'config.php';

if (!empty($GLOBALS['_FW_CONFIG']['db'])) {
    DB::configure($GLOBALS['_FW_CONFIG']['db']);
}

function fwConfig(?string $key = null, $default = null)
{
    $config = $GLOBALS['_FW_CONFIG'] ?? [];

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

function fwBaseUrl(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $base === '/' ? '' : $base;
}

function fwUrl(string $path = ''): string
{
    $path = trim($path);

    if ($path === '') {
        return fwBaseUrl() === '' ? '/' : fwBaseUrl();
    }

    if (
        str_starts_with($path, 'http://') ||
        str_starts_with($path, 'https://') ||
        str_starts_with($path, '#') ||
        str_starts_with($path, '?')
    ) {
        return $path;
    }

    return (fwBaseUrl() === '' ? '' : fwBaseUrl()) . '/' . ltrim($path, '/');
}

function fwCurrentPath(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = $uri ?: '/';

    $base = fwBaseUrl();

    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    $uri = trim($uri, '/');

    return $uri === '' ? 'index' : $uri;
}

function onapi(string $name): bool
{
    $api = $_GET['api'] ?? null;

    if (!is_string($api)) {
        return false;
    }

    return trim($api) === $name;
}

function route(string $path = '', array $query = []): string
{
    $url = fwUrl($path);

    if (!empty($query)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function fwDataDir(): string
{
    $dir = ROOT . 'datas/';

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return $dir;
}
function fwLogDir(): string
{
    $dir = fwDataDir() . 'logs/';

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return $dir;
}

function fwLogFile(string $name = 'php.log'): string
{
    $name = trim($name);

    if ($name === '') {
        $name = 'php.log';
    }

    return fwLogDir() . basename($name);
}

function fwRequestMethod(): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    return is_string($method) ? $method : 'CLI';
}

function fwRequestUri(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    return is_string($uri) ? $uri : '';
}

function fwPhpErrorTypeName(int $type): string
{
    return match ($type) {
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        default => 'E_UNKNOWN',
    };
}

function fwLogKey(array $parts): string
{
    return sha1(json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function fwLogNormalizeValue($value)
{
    if (is_array($value) || is_object($value)) {
        return print_r($value, true);
    }

    if (is_bool($value)) {
        return $value;
    }

    if ($value === null) {
        return null;
    }

    return (string) $value;
}

function fwLogSummary(array $entry): string
{
    $firstAt = (string) ($entry['first_at'] ?? '');
    $lastAt = (string) ($entry['last_at'] ?? '');
    $count = (int) ($entry['count'] ?? 1);

    return '[' . $firstAt . '] - [' . $lastAt . '] ' . $count . ' times';
}

function fwParseLogEntries(string $content): array
{
    $entries = [];
    $lines = preg_split("/\\r\\n|\\n|\\r/", $content);
    $current = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '[[ERROR_LOG]]') {
            $current = [];
            continue;
        }

        if ($trimmed === '[[/ERROR_LOG]]') {
            if (is_array($current) && !empty($current['key'])) {
                $entries[] = $current;
            }

            $current = null;
            continue;
        }

        if (!is_array($current)) {
            continue;
        }

        if ($trimmed === '' || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $current[$name] = $decoded;
            continue;
        }

        $current[$name] = $value;
    }

    return $entries;
}

function fwBuildLogEntriesText(array $entries): string
{
    $text = '';

    foreach ($entries as $entry) {
        if (empty($entry['key'])) {
            continue;
        }

        $text .= "[[ERROR_LOG]]\n";
        $text .= fwLogSummary($entry) . "\n";

        $order = [
            'key',
            'title',
            'type',
            'class',
            'message',
            'file',
            'line',
            'method',
            'url',
            'trace',
            'count',
            'first_at',
            'last_at',
        ];

        foreach ($order as $field) {
            if (!array_key_exists($field, $entry)) {
                continue;
            }

            $text .= $field . '=' . json_encode(
                $entry[$field],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . "\n";
        }

        foreach ($entry as $field => $value) {
            if (in_array($field, $order, true)) {
                continue;
            }

            $text .= $field . '=' . json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . "\n";
        }

        $text .= "[[/ERROR_LOG]]\n\n";
    }

    return $text;
}

function fwFindLogEntryIndex(array $entries, string $key): int
{
    foreach ($entries as $index => $entry) {
        if (($entry['key'] ?? '') === $key) {
            return $index;
        }
    }

    return -1;
}

function fwUpdateLogEntry(string $fileName, array $entry): void
{
    static $isWriting = false;

    if ($isWriting) {
        return;
    }

    $isWriting = true;

    $file = fwLogFile($fileName);
    $handle = @fopen($file, 'c+');

    if (!$handle) {
        $isWriting = false;
        return;
    }

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        $isWriting = false;
        return;
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    $entries = fwParseLogEntries($content === false ? '' : $content);

    $now = date('Y-m-d H:i:s');
    $index = fwFindLogEntryIndex($entries, (string) ($entry['key'] ?? ''));

    if ($index === -1) {
        $entry['count'] = 1;
        $entry['first_at'] = $now;
        $entry['last_at'] = $now;
        $entries[] = $entry;
    } else {
        $existing = $entries[$index];

        $entry['count'] = ((int) ($existing['count'] ?? 0)) + 1;
        $entry['first_at'] = $existing['first_at'] ?? $now;
        $entry['last_at'] = $now;

        $entries[$index] = array_merge($existing, $entry);
    }

    $text = fwBuildLogEntriesText($entries);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, $text);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $isWriting = false;
}

function fwLogPhpError(array $entry): void
{
    if (!fwConfig('save_errors', true)) {
        return;
    }

    $entry['message'] = fwLogNormalizeValue($entry['message'] ?? '');
    $entry['file'] = fwLogNormalizeValue($entry['file'] ?? '');
    $entry['line'] = (int) ($entry['line'] ?? 0);
    $entry['method'] = fwLogNormalizeValue($entry['method'] ?? fwRequestMethod());
    $entry['url'] = fwLogNormalizeValue($entry['url'] ?? fwRequestUri());

    if (array_key_exists('trace', $entry)) {
        $entry['trace'] = fwLogNormalizeValue($entry['trace']);
    }

    fwUpdateLogEntry(
        fwConfig('php_log_file', 'php.log'),
        $entry
    );
}

function fwRenderErrorPage(int $statusCode = 500): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    $pagePath = APP . 'pages/';

    if (is_file($pagePath . $statusCode . '.php')) {
        require $pagePath . $statusCode . '.php';
        exit;
    }

    if (is_file($pagePath . $statusCode . '.html')) {
        require $pagePath . $statusCode . '.html';
        exit;
    }

    echo $statusCode . ' - Wystąpił błąd';
    exit;
}

function fwHandlePhpError(int $severity, string $message, string $file = '', int $line = 0): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }

    fwLogPhpError([
        'key' => fwLogKey([
            'title' => 'PHP ERROR',
            'type' => fwPhpErrorTypeName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]),
        'title' => 'PHP ERROR',
        'type' => fwPhpErrorTypeName($severity),
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'method' => fwRequestMethod(),
        'url' => fwRequestUri(),
    ]);

    if (fwConfig('debug', false)) {
        return false;
    }

    return true;
}

function fwHandlePhpException(Throwable $e): void
{
    fwLogPhpError([
        'key' => fwLogKey([
            'title' => 'PHP EXCEPTION',
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]),
        'title' => 'PHP EXCEPTION',
        'class' => get_class($e),
        'type' => 'EXCEPTION',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => fwRequestMethod(),
        'url' => fwRequestUri(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (fwConfig('debug', false)) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        exit;
    }

    fwRenderErrorPage(500);
}

function fwHandlePhpShutdown(): void
{
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    if (!in_array($error['type'] ?? null, $fatalTypes, true)) {
        return;
    }

    fwLogPhpError([
        'key' => fwLogKey([
            'title' => 'PHP FATAL',
            'type' => fwPhpErrorTypeName((int) ($error['type'] ?? 0)),
            'message' => (string) ($error['message'] ?? ''),
            'file' => (string) ($error['file'] ?? ''),
            'line' => (int) ($error['line'] ?? 0),
        ]),
        'title' => 'PHP FATAL',
        'type' => fwPhpErrorTypeName((int) ($error['type'] ?? 0)),
        'message' => (string) ($error['message'] ?? ''),
        'file' => (string) ($error['file'] ?? ''),
        'line' => (int) ($error['line'] ?? 0),
        'method' => fwRequestMethod(),
        'url' => fwRequestUri(),
    ]);

    if (fwConfig('debug', false)) {
        return;
    }

    fwRenderErrorPage(500);
}

function fwSetupPhpErrorHandling(): void
{
    static $isInitialized = false;

    if ($isInitialized) {
        return;
    }

    $isInitialized = true;

    $debug = fwConfig('debug', false);

    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('display_startup_errors', $debug ? '1' : '0');
    ini_set('html_errors', '1');
    ini_set('log_errors', '0');

    set_error_handler('fwHandlePhpError');
    set_exception_handler('fwHandlePhpException');
    register_shutdown_function('fwHandlePhpShutdown');
}

fwSetupPhpErrorHandling();