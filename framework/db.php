<?php

require_once __DIR__ . '/dbScheme.php';

final class DB
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;

        DBScheme::configure($config);

        if (DBScheme::mode() === 2) {
            self::connect();
        }

        register_shutdown_function(function ()
        {
            DB::closeConnection();
        });
    }

    private static function connect(): PDO
    {
        $pdo = self::connectRaw();

        if (DBScheme::mode() !== 0) {
            DBScheme::ensure($pdo);
        }

        return $pdo;
    }

    private static function connectRaw(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        DBScheme::ensureDatabaseExists();

        self::$pdo = new PDO(
            self::$config['dsn'] ?? '',
            self::$config['user'] ?? '',
            self::$config['pass'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return self::$pdo;
    }

    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function normalizeSql(string $sql, array $params): array
    {
        $sql = str_replace('\?', '__FW_QUESTION__', $sql);

        if (empty($params)) {
            return [str_replace('__FW_QUESTION__', '?', $sql), []];
        }

        if (self::isAssoc($params)) {
            return [str_replace('__FW_QUESTION__', '?', $sql), $params];
        }

        $namedParams = [];
        $index = 0;
        $length = strlen($sql);
        $newSql = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === '?') {
                $paramName = ':p' . $index;
                $newSql .= $paramName;
                $namedParams[$paramName] = $params[$index] ?? null;
                $index++;
                continue;
            }

            $newSql .= $char;
        }

        $newSql = str_replace('__FW_QUESTION__', '?', $newSql);

        return [$newSql, $namedParams];
    }

    private static function saveErrorsEnabled(): bool
    {
        if (!function_exists('fwConfig')) {
            return true;
        }

        return (bool) fwConfig('save_errors', true);
    }

    private static function requestMethod(): string
    {
        if (function_exists('fwRequestMethod')) {
            return (string) fwRequestMethod();
        }

        return is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
    }

    private static function requestUri(): string
    {
        if (function_exists('fwRequestUri')) {
            return (string) fwRequestUri();
        }

        return is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';
    }

    private static function normalizeLogValue($value): string
    {
        if (function_exists('fwLogNormalizeValue')) {
            $value = fwLogNormalizeValue($value);
        }

        if (is_array($value) || is_object($value)) {
            return print_r($value, true);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private static function logDir(): string
    {
        $dir = defined('ROOT') ? ROOT . 'datas/logs/' : dirname(__DIR__) . '/datas/logs/';
        $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function sqlLogFile(): string
    {
        $name = function_exists('fwConfig') ? (string) fwConfig('sql_log_file', 'sql.log') : 'sql.log';

        return self::logDir() . basename($name);
    }

    private static function logSql(string $title, array $context = []): void
    {
        if (!self::saveErrorsEnabled()) {
            return;
        }

        $lines = [];
        $lines[] = '[' . date('Y-m-d H:i:s') . '] ' . $title;

        foreach ($context as $key => $value) {
            $lines[] = $key . ': ' . self::normalizeLogValue($value);
        }

        $lines[] = str_repeat('-', 80);

        @file_put_contents(
            self::sqlLogFile(),
            implode(PHP_EOL, $lines) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function shouldRetryAfterDbScheme(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = strtoupper((string) $e->getCode());

        if ($code === '42S02' || $code === '42S22') {
            return true;
        }

        foreach ([
            'base table or view not found',
            "doesn't exist",
            'unknown column',
            'no such table',
            'undefined table',
            'undefined column',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function runQuery(string $mode, string $sql, array $params = [], bool $allowRetry = true)
    {
        [$sql, $params] = self::normalizeSql($sql, $params);

        try {
            $stmt = self::connect()->prepare($sql);
            $stmt->execute($params);

            if ($mode === 'query') {
                return $stmt->fetchAll();
            }

            if ($mode === 'get') {
                $row = $stmt->fetch();

                return $row === false ? null : $row;
            }

            return $stmt->rowCount();
        } catch (Throwable $e) {
            self::logSql('SQL ERROR', [
                'method' => self::requestMethod(),
                'url' => self::requestUri(),
                'mode' => $mode,
                'sql' => $sql,
                'params' => $params,
                'retry_after_db_scheme' => $allowRetry && self::shouldRetryAfterDbScheme($e) ? 'true' : 'false',
                'error' => $e->getMessage(),
                'code' => (string) $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if ($allowRetry && self::shouldRetryAfterDbScheme($e) && DBScheme::mode() !== 0) {
                DBScheme::ensure(self::connectRaw(), true);
                return self::runQuery($mode, $sql, $params, false);
            }

            throw $e;
        }
    }

    public static function query(string $sql, array $params = []): array
    {
        return self::runQuery('query', $sql, $params);
    }

    public static function get(string $sql, array $params = []): ?array
    {
        if (!preg_match('/\blimit\b/i', $sql)) {
            $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' LIMIT 1';
        }

        return self::runQuery('get', $sql, $params);
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::runQuery('execute', $sql, $params);
    }

    public static function lastID(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function closeConnection(): void
    {
        self::$pdo = null;
    }
}

function DB_QUERY(string $sql, array $params = []): array
{
    return DB::query($sql, $params);
}

function DB_GET(string $sql, array $params = []): ?array
{
    return DB::get($sql, $params);
}

function DB_EXECUTE(string $sql, array $params = []): int
{
    return DB::execute($sql, $params);
}

function DB_LAST_ID(): string
{
    return DB::lastID();
}

function DB_TABLE(string $name): array
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

    return DB_QUERY("SELECT * FROM `{$safe}`");
}

function DB_SELECT(string $table, array $fields = [], array $where = [], string $end = ''): array
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

    if (empty($fields)) {
        $fieldSql = '*';
    } else {
        $preparedFields = [];

        foreach ($fields as $field) {
            if ($field === '*') {
                $preparedFields[] = '*';
                continue;
            }

            if (preg_match('/[^a-zA-Z0-9_]/', $field)) {
                $preparedFields[] = $field;
                continue;
            }

            $preparedFields[] = "`{$field}`";
        }

        $fieldSql = implode(', ', $preparedFields);
    }

    $sql = "SELECT {$fieldSql} FROM `{$safeTable}`";
    $params = [];

    if (!empty($where)) {
        $parts = [];
        $index = 0;

        foreach ($where as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

            if ($value === null) {
                $parts[] = "`{$safeKey}` IS NULL";
                continue;
            }

            $paramName = ':w' . $index;
            $parts[] = "`{$safeKey}` = {$paramName}";
            $params[$paramName] = $value;
            $index++;
        }

        if (!empty($parts)) {
            $sql .= ' WHERE ' . implode(' AND ', $parts);
        }
    }

    if ($end !== '') {
        $sql .= ' ' . trim($end);
    }

    return DB_QUERY($sql, $params);
}

function DB_INSERT(string $table, array $data): string
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columns = [];
    $placeholders = [];
    $params = [];
    $index = 0;

    foreach ($data as $key => $value) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $paramName = ':v' . $index;
        $columns[] = "`{$safeKey}`";
        $placeholders[] = $paramName;
        $params[$paramName] = $value;
        $index++;
    }

    $sql = "INSERT INTO `{$safeTable}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ')';
    DB_EXECUTE($sql, $params);

    return DB_LAST_ID();
}

function DB_UPDATE(string $table, array $data, array $where = []): int
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $setParts = [];
    $whereParts = [];
    $params = [];
    $index = 0;

    foreach ($data as $key => $value) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $paramName = ':s' . $index;
        $setParts[] = "`{$safeKey}` = {$paramName}";
        $params[$paramName] = $value;
        $index++;
    }

    foreach ($where as $key => $value) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        if ($value === null) {
            $whereParts[] = "`{$safeKey}` IS NULL";
            continue;
        }

        $paramName = ':w' . $index;
        $whereParts[] = "`{$safeKey}` = {$paramName}";
        $params[$paramName] = $value;
        $index++;
    }

    $sql = "UPDATE `{$safeTable}` SET " . implode(', ', $setParts);

    if (!empty($whereParts)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

    return DB_EXECUTE($sql, $params);
}