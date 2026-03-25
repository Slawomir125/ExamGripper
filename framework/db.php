<?php

final class DB
{
    private static ?PDO $pdo = null;
    private static array $config = [];
    private static bool $schemaChecked = false;
    private static bool $schemaRunning = false;

    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$schemaChecked = false;

        register_shutdown_function(function () {
            DB::close();
        });
    }

    private static function connect(): PDO
    {
        $pdo = self::connectRaw();
        self::ensureSchema();

        return $pdo;
    }

    private static function connectRaw(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

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

    private static function dataDir(): string
    {
        $dir = defined('ROOT') ? ROOT . 'datas/' : dirname(__DIR__) . '/datas/';
        $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function logDir(): string
    {
        $dir = self::dataDir() . 'logs/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function schemaSourceDir(): string
    {
        $dir = self::$config['schema_dir'] ?? (defined('APP') ? APP . 'db/' : dirname(__DIR__) . '/app/db/');
        return rtrim(str_replace('\\', '/', (string) $dir), '/') . '/';
    }

    private static function schemaStateDir(): string
    {
        $dir = self::$config['schema_state_dir'] ?? (self::dataDir() . 'dbScheme/');
        $dir = rtrim(str_replace('\\', '/', (string) $dir), '/') . '/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function schemaStateFile(): string
    {
        return self::schemaStateDir() . 'state.log';
    }

    private static function schemaEnabled(): bool
    {
        return (bool) (self::$config['auto_schema'] ?? true);
    }

    private static function ensureSchema(bool $force = false): void
    {
        if (!self::schemaEnabled()) {
            self::$schemaChecked = true;
            return;
        }

        if (self::$schemaRunning) {
            return;
        }

        if (self::$schemaChecked && !$force) {
            return;
        }

        self::$schemaRunning = true;

        try {
            self::syncSchemaFiles($force);
            self::$schemaChecked = true;
        } finally {
            self::$schemaRunning = false;
        }
    }

    private static function syncSchemaFiles(bool $force = false): void
    {
        $schemaDir = self::schemaSourceDir();

        if (!is_dir($schemaDir)) {
            return;
        }

        $files = self::findSchemaFiles($schemaDir);

        if (empty($files)) {
            return;
        }

        $state = self::readSchemaState();

        foreach ($files as $relativePath => $fullPath) {
            $hash = sha1_file($fullPath);

            if ($hash === false) {
                continue;
            }

            $currentHash = $state[$relativePath]['hash'] ?? null;

            if (!$force && $currentHash === $hash) {
                continue;
            }

            self::applySchemaFile($relativePath, $fullPath);

            $state = self::updateSchemaStateEntry($state, $relativePath, $hash);
            self::writeSchemaState($state);
        }
    }

    private static function findSchemaFiles(string $schemaDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($schemaDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }

            if (strtolower($item->getExtension()) !== 'sql') {
                continue;
            }

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $relativePath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($schemaDir))), '/');

            if ($relativePath === '') {
                continue;
            }

            $files[$relativePath] = $fullPath;
        }

        ksort($files);

        return $files;
    }

    private static function readSchemaState(): array
    {
        $file = self::schemaStateFile();

        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return [];
        }

        $entries = self::parseSchemaEntries($content);
        $state = [];

        foreach ($entries as $entry) {
            $path = (string) ($entry['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $state[$path] = $entry;
        }

        return $state;
    }

    private static function parseSchemaEntries(string $content): array
    {
        $entries = [];
        $lines = preg_split("/\\r\\n|\\n|\\r/", $content);
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '[[DB_SCHEME]]') {
                $current = [];
                continue;
            }

            if ($trimmed === '[[/DB_SCHEME]]') {
                if (is_array($current) && !empty($current['path'])) {
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

    private static function updateSchemaStateEntry(array $state, string $path, string $hash): array
    {
        $now = date('Y-m-d H:i:s');
        $existing = $state[$path] ?? [];

        $state[$path] = [
            'path' => $path,
            'hash' => $hash,
            'count' => ((int) ($existing['count'] ?? 0)) + 1,
            'first_at' => $existing['first_at'] ?? $now,
            'last_at' => $now,
            'status' => 'ok',
        ];

        return $state;
    }

    private static function writeSchemaState(array $state): void
    {
        ksort($state);

        $text = '';

        foreach ($state as $entry) {
            $text .= "[[DB_SCHEME]]\n";
            $text .= '[' . ($entry['first_at'] ?? '') . '] - [' . ($entry['last_at'] ?? '') . '] ' . ((int) ($entry['count'] ?? 1)) . " times\n";

            foreach (['path', 'hash', 'count', 'first_at', 'last_at', 'status'] as $field) {
                if (!array_key_exists($field, $entry)) {
                    continue;
                }

                $text .= $field . '=' . json_encode(
                    $entry[$field],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . "\n";
            }

            $text .= "[[/DB_SCHEME]]\n\n";
        }

        @file_put_contents(self::schemaStateFile(), $text, LOCK_EX);
    }

    private static function applySchemaFile(string $relativePath, string $fullPath): void
    {
        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new RuntimeException('Nie udało się odczytać pliku DB schema: ' . $relativePath);
        }

        $definition = self::parseCreateTableDefinition($content);

        if ($definition === null) {
            self::runSqlStatements($content, $relativePath);
            return;
        }

        if (!self::tableExists($definition['table'])) {
            self::runSqlStatements($content, $relativePath);
            self::verifyColumnsExist($definition);
            return;
        }

        self::syncMissingColumns($definition);
        self::verifyColumnsExist($definition);
    }

    private static function runSqlStatements(string $content, string $relativePath): void
    {
        $statements = self::splitSqlStatements($content);

        foreach ($statements as $statement) {
            if (trim($statement) === '') {
                continue;
            }

            try {
                self::execRaw($statement);
            } catch (Throwable $e) {
                self::logSqlError('SQL SCHEMA ERROR', $statement, [], $e, [
                    'schema_file' => $relativePath,
                ]);

                throw $e;
            }
        }
    }

    private static function parseCreateTableDefinition(string $sql): ?array
    {
        $sql = ltrim($sql, "\xEF\xBB\xBF");

        if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?((?:`[^`]+`|[a-zA-Z0-9_]+)(?:\.(?:`[^`]+`|[a-zA-Z0-9_]+))?)/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
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

        if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT|SPATIAL|CHECK)\b/i', $part)) {
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

        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s+(.+)$/s', $part, $matches)) {
            return null;
        }

        return [
            'name' => $matches[1],
            'definition' => trim($matches[2]),
        ];
    }

    private static function tableExists(string $table): bool
    {
        $sql = "
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            LIMIT 1
        ";

        $stmt = self::connectRaw()->prepare($sql);
        $stmt->execute([':table' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private static function getExistingColumns(string $table): array
    {
        $sql = "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
        ";

        $stmt = self::connectRaw()->prepare($sql);
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

    private static function syncMissingColumns(array $definition): void
    {
        $table = $definition['table'];
        $columns = $definition['columns'] ?? [];
        $existing = self::getExistingColumns($table);

        foreach ($columns as $columnName => $columnDefinition) {
            if (isset($existing[$columnName])) {
                continue;
            }

            $sql = "ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD COLUMN `" . str_replace('`', '``', $columnName) . "` " . $columnDefinition;

            try {
                self::execRaw($sql);
            } catch (Throwable $e) {
                self::logSqlError('SQL SCHEMA ERROR', $sql, [], $e, [
                    'schema_file' => $table . '.sql',
                    'schema_action' => 'add_column',
                    'schema_column' => $columnName,
                ]);

                throw $e;
            }
        }
    }

    private static function verifyColumnsExist(array $definition): void
    {
        $table = $definition['table'];
        $columns = $definition['columns'] ?? [];
        $existing = self::getExistingColumns($table);

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

    private static function queryRaw(string $sql, array $params = []): array
    {
        [$sql, $params] = self::normalizeSql($sql, $params);

        $stmt = self::connectRaw()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function execRaw(string $sql): void
    {
        self::connectRaw()->exec($sql);
    }

    private static function shouldRetryAfterSchema(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = strtoupper((string) $e->getCode());

        if ($code === '42S02' || $code === '42S22') {
            return true;
        }

        $needles = [
            'base table or view not found',
            "doesn't exist",
            'unknown column',
            'no such table',
            'undefined table',
            'undefined column',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function sqlLogFilePath(): string
    {
        $fileName = function_exists('fwConfig') ? (string) fwConfig('sql_log_file', 'sql.log') : 'sql.log';
        return self::logDir() . basename($fileName);
    }

    private static function logSqlError(string $title, string $sql, array $params, Throwable $e, array $extra = []): void
    {
        if (!function_exists('fwConfig') || !fwConfig('save_errors', true)) {
            return;
        }

        if (!function_exists('fwUpdateLogEntry') || !function_exists('fwLogKey') || !function_exists('fwLogNormalizeValue')) {
            return;
        }

        $entry = [
            'key' => fwLogKey([
                'title' => $title,
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'sql' => $sql,
                'code' => (string) $e->getCode(),
            ]),
            'title' => $title,
            'type' => get_class($e),
            'message' => fwLogNormalizeValue($e->getMessage()),
            'file' => fwLogNormalizeValue($e->getFile()),
            'line' => (int) $e->getLine(),
            'method' => function_exists('fwRequestMethod') ? fwRequestMethod() : '',
            'url' => function_exists('fwRequestUri') ? fwRequestUri() : '',
            'sql' => fwLogNormalizeValue($sql),
            'params' => fwLogNormalizeValue($params),
            'code' => fwLogNormalizeValue((string) $e->getCode()),
        ];

        foreach ($extra as $key => $value) {
            $entry[$key] = fwLogNormalizeValue($value);
        }

        self::writeSqlLogEntry($entry);
    }

    private static function readSqlLogEntries(): array
    {
        $file = self::sqlLogFilePath();

        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return [];
        }

        if (!function_exists('fwParseLogEntries')) {
            return [];
        }

        return fwParseLogEntries($content);
    }

    private static function buildSqlLogEntriesText(array $entries): string
    {
        $text = '';

        foreach ($entries as $entry) {
            if (empty($entry['key'])) {
                continue;
            }

            $text .= "[[ERROR_LOG]]\n";

            if (function_exists('fwLogSummary')) {
                $text .= fwLogSummary($entry) . "\n";
            }

            $order = [
                'key',
                'title',
                'type',
                'message',
                'file',
                'line',
                'method',
                'url',
                'sql',
                'params',
                'code',
                'schema_file',
                'schema_action',
                'schema_column',
                'mode',
                'retry_after_schema',
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

    private static function writeSqlLogEntry(array $entry): void
    {
        static $isWriting = false;

        if ($isWriting) {
            return;
        }

        $isWriting = true;

        $file = self::sqlLogFilePath();
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
        $entries = [];

        if ($content !== false && trim($content) !== '' && function_exists('fwParseLogEntries')) {
            $entries = fwParseLogEntries($content);
        }

        $now = date('Y-m-d H:i:s');
        $index = -1;

        foreach ($entries as $i => $existing) {
            if (($existing['key'] ?? '') === ($entry['key'] ?? '')) {
                $index = $i;
                break;
            }
        }

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

        $text = self::buildSqlLogEntriesText($entries);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $text);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $isWriting = false;
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
            self::logSqlError('SQL ERROR', $sql, $params, $e, [
                'mode' => $mode,
                'retry_after_schema' => $allowRetry && self::shouldRetryAfterSchema($e),
            ]);

            if ($allowRetry && self::shouldRetryAfterSchema($e)) {
                self::ensureSchema(true);
                return self::runQuery($mode, $sql, $params, false);
            }

            throw $e;
        }
    }

    public static function close(): void
    {
        self::$pdo = null;
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

    $sql = "INSERT INTO `{$safeTable}` (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

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
        $sql .= " WHERE " . implode(' AND ', $whereParts);
    }

    return DB_EXECUTE($sql, $params);
}