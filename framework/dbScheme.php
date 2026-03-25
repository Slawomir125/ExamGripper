<?php

final class DBScheme
{
    private static array $config = [];
    private static bool $checked = false;
    private static bool $running = false;

    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$checked = false;
    }

    public static function mode(): int
    {
        $mode = self::$config['db_scheme_check'] ?? null;

        if ($mode === null && function_exists('fwConfig')) {
            $mode = fwConfig('db_scheme_check', 1);
        }

        $mode = (int) $mode;

        if ($mode < 0 || $mode > 2) {
            return 1;
        }

        return $mode;
    }

    public static function ensureDatabaseExists(): void
    {
        $dsn = (string) (self::$config['dsn'] ?? '');

        if ($dsn === '' || stripos($dsn, 'mysql:') !== 0) {
            return;
        }

        if (!preg_match('/dbname=([^;]+)/i', $dsn, $matches)) {
            return;
        }

        $dbName = trim($matches[1]);

        if ($dbName === '') {
            return;
        }

        $serverDsn = preg_replace('/;?dbname=[^;]+/i', '', $dsn);
        $serverDsn = rtrim((string) $serverDsn, ';');

        try {
            $pdo = new PDO(
                $serverDsn,
                self::$config['user'] ?? '',
                self::$config['pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $sql = 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';
            $pdo->exec($sql);

            self::log('DB CREATE DATABASE OK', [
                'database' => $dbName,
                'sql' => $sql,
            ]);
        } catch (Throwable $e) {
            self::log('DB CREATE DATABASE ERROR', [
                'database' => $dbName,
                'dsn' => $serverDsn,
                'error' => $e->getMessage(),
                'code' => (string) $e->getCode(),
            ]);
        }
    }

    public static function ensure(PDO $pdo, bool $force = false): void
    {
        if (self::mode() === 0) {
            return;
        }

        if (self::$running) {
            return;
        }

        if (self::$checked && !$force && self::mode() === 1) {
            return;
        }

        self::$running = true;

        try {
            self::processFiles($pdo);
            self::$checked = true;
        } catch (Throwable $e) {
            self::log('DB SCHEME FATAL', [
                'method' => self::requestMethod(),
                'url' => self::requestUri(),
                'error' => $e->getMessage(),
                'code' => (string) $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            self::$running = false;
        }
    }

    private static function processFiles(PDO $pdo): void
    {
        $dir = self::sourceDir();

        if (!is_dir($dir)) {
            self::log('DB SCHEME SKIP', [
                'reason' => 'Directory does not exist',
                'dir' => $dir,
            ]);
            return;
        }

        $files = self::findFiles($dir);
        $state = self::readState();

        self::log('DB SCHEME START', [
            'dir' => $dir,
            'files' => count($files),
            'mode' => self::mode(),
        ]);

        foreach ($files as $relativePath => $fullPath) {
            self::processFile($pdo, $relativePath, $fullPath, $state);
        }

        self::writeState($state);

        self::log('DB SCHEME END', [
            'dir' => $dir,
            'files' => count($files),
        ]);
    }

    private static function processFile(PDO $pdo, string $relativePath, string $fullPath, array &$state): void
    {
        $content = file_get_contents($fullPath);

        if ($content === false) {
            self::log('DB SCHEME FILE ERROR', [
                'file' => $relativePath,
                'action' => 'read',
                'result' => 'failed',
            ]);
            return;
        }

        $statements = self::splitSqlStatements($content);

        self::log('DB SCHEME FILE', [
            'file' => $relativePath,
            'statements' => count($statements),
        ]);

        foreach ($statements as $index => $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            $statementHash = sha1($statement);
            $stateKey = $relativePath . '::' . $statementHash;
            $alreadyDone = !empty($state[$stateKey]['done']);

            if ($alreadyDone) {
                self::log('DB SCHEME STATEMENT SKIP', [
                    'file' => $relativePath,
                    'statement_index' => $index + 1,
                    'hash' => $statementHash,
                    'reason' => 'already executed',
                ]);
                continue;
            }

            self::log('DB SCHEME STATEMENT RUN', [
                'file' => $relativePath,
                'statement_index' => $index + 1,
                'hash' => $statementHash,
                'sql' => $statement,
            ]);

            try {
                self::applyStatement($pdo, $statement);

                $now = date('Y-m-d H:i:s');
                $existing = $state[$stateKey] ?? [];

                $state[$stateKey] = [
                    'key' => $stateKey,
                    'file' => $relativePath,
                    'hash' => $statementHash,
                    'sql' => $statement,
                    'done' => true,
                    'count' => ((int) ($existing['count'] ?? 0)) + 1,
                    'first_at' => $existing['first_at'] ?? $now,
                    'last_at' => $now,
                ];

                self::log('DB SCHEME STATEMENT OK', [
                    'file' => $relativePath,
                    'statement_index' => $index + 1,
                    'hash' => $statementHash,
                ]);
            } catch (Throwable $e) {
                self::log('DB SCHEME STATEMENT ERROR', [
                    'file' => $relativePath,
                    'statement_index' => $index + 1,
                    'hash' => $statementHash,
                    'sql' => $statement,
                    'error' => $e->getMessage(),
                    'code' => (string) $e->getCode(),
                ]);
            }
        }
    }

    private static function applyStatement(PDO $pdo, string $statement): void
    {
        $definition = self::parseCreateTableDefinition($statement);

        if ($definition === null) {
            $pdo->exec($statement);
            return;
        }

        if (!self::tableExists($pdo, $definition['table'])) {
            $pdo->exec($statement);
            self::verifyColumnsExist($pdo, $definition);
            return;
        }

        self::syncMissingColumns($pdo, $definition);
        self::verifyColumnsExist($pdo, $definition);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute([':table' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private static function getExistingColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([':table' => $table]);

        $columns = [];

        foreach ($stmt->fetchAll() as $row) {
            $name = (string) ($row['COLUMN_NAME'] ?? '');

            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        return $columns;
    }

    private static function syncMissingColumns(PDO $pdo, array $definition): void
    {
        $table = $definition['table'];
        $columns = $definition['columns'] ?? [];
        $existing = self::getExistingColumns($pdo, $table);

        foreach ($columns as $columnName => $columnDefinition) {
            if (isset($existing[$columnName])) {
                continue;
            }

            $sql = 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $columnName) . '` ' . $columnDefinition;
            $pdo->exec($sql);

            self::log('DB SCHEME COLUMN ADD', [
                'table' => $table,
                'column' => $columnName,
                'sql' => $sql,
            ]);
        }
    }

    private static function verifyColumnsExist(PDO $pdo, array $definition): void
    {
        $table = $definition['table'];
        $columns = $definition['columns'] ?? [];
        $existing = self::getExistingColumns($pdo, $table);
        $missing = [];

        foreach ($columns as $columnName => $columnDefinition) {
            if (!isset($existing[$columnName])) {
                $missing[] = $columnName;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException(
                'Brakuje kolumn po synchronizacji tabeli `' . $table . '`: ' . implode(', ', $missing)
            );
        }
    }

    private static function findFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }

            if (strtolower($item->getExtension()) !== 'sql') {
                continue;
            }

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $relativePath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($dir))), '/');

            if ($relativePath === '') {
                continue;
            }

            $files[$relativePath] = $fullPath;
        }

        ksort($files);

        return $files;
    }

    private static function readState(): array
    {
        $file = self::stateFile();

        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return [];
        }

        $entries = [];
        $lines = preg_split("/\\r\\n|\\n|\\r/", $content);
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '[[DB_SCHEME_STATE]]') {
                $current = [];
                continue;
            }

            if ($trimmed === '[[/DB_SCHEME_STATE]]') {
                if (is_array($current) && !empty($current['key'])) {
                    $entries[$current['key']] = $current;
                }

                $current = null;
                continue;
            }

            if (!is_array($current) || $trimmed === '' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $decoded = json_decode($value, true);
            $current[$name] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $entries;
    }

    private static function writeState(array $state): void
    {
        ksort($state);
        $text = '';

        foreach ($state as $entry) {
            $text .= "[[DB_SCHEME_STATE]]\n";

            foreach (['key', 'file', 'hash', 'sql', 'done', 'count', 'first_at', 'last_at'] as $field) {
                if (!array_key_exists($field, $entry)) {
                    continue;
                }

                $text .= $field . '=' . json_encode(
                    $entry[$field],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . "\n";
            }

            $text .= "[[/DB_SCHEME_STATE]]\n\n";
        }

        @file_put_contents(self::stateFile(), $text, LOCK_EX);
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

    private static function log(string $title, array $context = []): void
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
            self::logFile(),
            implode(PHP_EOL, $lines) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function logFile(): string
    {
        $name = function_exists('fwConfig') ? (string) fwConfig('db_scheme_log_file', 'dbScheme.log') : 'dbScheme.log';

        return self::logDir() . basename($name);
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

    private static function stateDir(): string
    {
        $dir = defined('ROOT') ? ROOT . 'datas/dbScheme/' : dirname(__DIR__) . '/datas/dbScheme/';
        $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function stateFile(): string
    {
        return self::stateDir() . 'state.log';
    }

    private static function sourceDir(): string
    {
        $dir = self::$config['db_scheme_dir'] ?? null;

        if ($dir === null && function_exists('fwConfig')) {
            $dir = fwConfig('db_scheme_dir', null);
        }

        if (!is_string($dir) || trim($dir) === '') {
            $dir = defined('APP') ? APP . 'db/' : dirname(__DIR__) . '/app/db/';
        }

        return rtrim(str_replace('\\', '/', $dir), '/') . '/';
    }

    private static function parseCreateTableDefinition(string $sql): ?array
    {
        $sql = ltrim($sql, "\xEF\xBB\xBF");

        if (!preg_match('/CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?((?:`[^`]+`|[a-zA-Z0-9_]+)(?:\\.(?:`[^`]+`|[a-zA-Z0-9_]+))?)/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rawTable = $matches[1][0];
        $tablePos = $matches[1][1];
        $table = self::normalizeTableName($rawTable);

        if ($table === '') {
            return null;
        }

        $openPos = strpos($sql, '(', $tablePos + strlen($rawTable));

        if ($openPos === false) {
            return null;
        }

        $closePos = self::findMatchingParenthesis($sql, $openPos);

        if ($closePos === null) {
            return null;
        }

        $body = substr($sql, $openPos + 1, $closePos - $openPos - 1);
        $parts = self::splitSqlList($body);
        $columns = [];

        foreach ($parts as $part) {
            $column = self::parseColumnDefinition($part);

            if ($column === null) {
                continue;
            }

            $columns[$column['name']] = $column['definition'];
        }

        return [
            'table' => $table,
            'columns' => $columns,
        ];
    }

    private static function normalizeTableName(string $rawTable): string
    {
        $rawTable = trim($rawTable);

        if (str_contains($rawTable, '.')) {
            $parts = explode('.', $rawTable);
            $rawTable = end($parts);
        }

        return trim($rawTable, " \t\n\r\0\x0B`");
    }

    private static function findMatchingParenthesis(string $sql, int $openPos): ?int
    {
        $depth = 0;
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = $openPos; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$inDouble && !$inBacktick) {
                if (!$inSingle) {
                    $inSingle = true;
                } elseif (!self::isEscaped($sql, $i)) {
                    $inSingle = false;
                }
            } elseif ($char === '"' && !$inSingle && !$inBacktick) {
                if (!$inDouble) {
                    $inDouble = true;
                } elseif (!self::isEscaped($sql, $i)) {
                    $inDouble = false;
                }
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($inSingle || $inDouble || $inBacktick) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function splitSqlList(string $body): array
    {
        $parts = [];
        $current = '';
        $length = strlen($body);
        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === "'" && !$inDouble && !$inBacktick) {
                if (!$inSingle) {
                    $inSingle = true;
                } elseif (!self::isEscaped($body, $i)) {
                    $inSingle = false;
                }

                $current .= $char;
                continue;
            }

            if ($char === '"' && !$inSingle && !$inBacktick) {
                if (!$inDouble) {
                    $inDouble = true;
                } elseif (!self::isEscaped($body, $i)) {
                    $inDouble = false;
                }

                $current .= $char;
                continue;
            }

            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $current .= $char;
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif ($char === ',' && $depth === 0) {
                    if (trim($current) !== '') {
                        $parts[] = trim($current);
                    }

                    $current = '';
                    continue;
                }
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private static function parseColumnDefinition(string $part): ?array
    {
        $part = trim($part);

        if ($part === '') {
            return null;
        }

        if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT|SPATIAL|CHECK)\\b/i', $part)) {
            return null;
        }

        if ($part[0] === '`') {
            $end = strpos($part, '`', 1);

            if ($end === false) {
                return null;
            }

            $name = substr($part, 1, $end - 1);
            $definition = trim(substr($part, $end + 1));

            if ($definition === '') {
                return null;
            }

            return [
                'name' => $name,
                'definition' => $definition,
            ];
        }

        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\\s+(.+)$/s', $part, $matches)) {
            return null;
        }

        return [
            'name' => $matches[1],
            'definition' => trim($matches[2]),
        ];
    }

    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }

                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($char === '-' && $next === '-' && self::isLineCommentStart($sql, $i)) {
                    $inLineComment = true;
                    $i++;
                    continue;
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick) {
                if (!$inSingle) {
                    $inSingle = true;
                } elseif (!self::isEscaped($sql, $i)) {
                    $inSingle = false;
                }

                $current .= $char;
                continue;
            }

            if ($char === '"' && !$inSingle && !$inBacktick) {
                if (!$inDouble) {
                    $inDouble = true;
                } elseif (!self::isEscaped($sql, $i)) {
                    $inDouble = false;
                }

                $current .= $char;
                continue;
            }

            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $current .= $char;
                continue;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }

                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }

    private static function isLineCommentStart(string $sql, int $index): bool
    {
        $prev = $index > 0 ? $sql[$index - 1] : '';
        $next = $index + 2 < strlen($sql) ? $sql[$index + 2] : '';

        return ($prev === '' || ctype_space($prev)) && ($next === '' || ctype_space($next));
    }

    private static function isEscaped(string $sql, int $index): bool
    {
        $slashes = 0;

        for ($i = $index - 1; $i >= 0; $i--) {
            if ($sql[$i] !== '\\') {
                break;
            }

            $slashes++;
        }

        return $slashes % 2 === 1;
    }
}