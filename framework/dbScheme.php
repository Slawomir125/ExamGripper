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

    public static function alwaysReadFiles(): bool
    {
        $value = self::$config['db_scheme_always_read_files'] ?? null;

        if ($value === null && function_exists('fwConfig')) {
            $value = fwConfig('db_scheme_always_read_files', false);
        }

        return (bool) $value;
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

            $existsStmt = $pdo->prepare(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1'
            );
            $existsStmt->execute([':name' => $dbName]);
            $exists = $existsStmt->fetchColumn() !== false;

            if ($exists) {
                return;
            }

            $sql = 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';
            $pdo->exec($sql);

            self::logChange(
                '__database__',
                'CREATE DATABASE',
                'CREATE DATABASE',
                $sql,
                $sql
            );
        } catch (Throwable $e) {
            self::logError('__database__', 'CREATE DATABASE', '', $e);
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
            self::logError('__dbScheme__', 'DB SCHEME', '', $e);
        } finally {
            self::$running = false;
        }
    }

    private static function processFiles(PDO $pdo): void
    {
        $dir = self::sourceDir();

        if (!is_dir($dir)) {
            return;
        }

        $files = self::findFiles($dir);
        $state = self::readState();

        foreach ($files as $relativePath => $fullPath) {
            $fileHash = sha1_file($fullPath);

            if ($fileHash === false) {
                continue;
            }

            $savedFileHash = $state['files'][$relativePath]['hash'] ?? null;

            if (!self::alwaysReadFiles() && $savedFileHash === $fileHash) {
                continue;
            }

            self::processFile($pdo, $relativePath, $fullPath, $fileHash, $state);
        }

        self::writeState($state);
    }

    private static function processFile(PDO $pdo, string $relativePath, string $fullPath, string $fileHash, array &$state): void
    {
        $content = file_get_contents($fullPath);

        if ($content === false) {
            self::logError($relativePath, 'READ FILE', '', new RuntimeException('Nie udało się odczytać pliku'));
            return;
        }

        $statements = self::splitSqlStatements($content);

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            $statementHash = sha1($statement);
            $stateKey = $relativePath . '::' . $statementHash;
            $alreadyDone = !empty($state['statements'][$stateKey]['done']);

            if ($alreadyDone) {
                continue;
            }

            try {
                [$targetCommand, $executedCommand, $targetSql, $executedSql, $changed] = self::applyStatement($pdo, $statement);

                if ($changed) {
                    self::logChange(
                        $relativePath,
                        $targetCommand,
                        $executedCommand,
                        $targetSql,
                        $executedSql
                    );
                }

                $now = date('Y-m-d H:i:s');
                $existing = $state['statements'][$stateKey] ?? [];

                $state['statements'][$stateKey] = [
                    'key' => $stateKey,
                    'file' => $relativePath,
                    'hash' => $statementHash,
                    'done' => true,
                    'count' => ((int) ($existing['count'] ?? 0)) + 1,
                    'first_at' => $existing['first_at'] ?? $now,
                    'last_at' => $now,
                ];
            } catch (Throwable $e) {
                self::logError($relativePath, self::extractCommand($statement), $statement, $e);
            }
        }

        $state['files'][$relativePath] = [
            'file' => $relativePath,
            'hash' => $fileHash,
            'last_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function applyStatement(PDO $pdo, string $statement): array
    {
        $definition = self::parseCreateTableDefinition($statement);

        if ($definition === null) {
            $targetCommand = self::extractCommand($statement);
            $affected = $pdo->exec($statement);

            $changed = self::statementShouldBeLogged($targetCommand, $affected);

            return [
                $targetCommand,
                $targetCommand,
                $statement,
                $statement,
                $changed,
            ];
        }

        if (!self::tableExists($pdo, $definition['table'])) {
            $pdo->exec($statement);

            return [
                'CREATE TABLE',
                'CREATE TABLE',
                $statement,
                $statement,
                true,
            ];
        }

        $existing = self::getExistingColumns($pdo, $definition['table']);

        foreach ($definition['columns'] as $columnName => $columnDefinition) {
            if (isset($existing[$columnName])) {
                continue;
            }

            $executedSql = 'ALTER TABLE `' . str_replace('`', '``', $definition['table']) . '` ADD COLUMN `' . str_replace('`', '``', $columnName) . '` ' . $columnDefinition;
            $pdo->exec($executedSql);

            return [
                'CREATE TABLE',
                'ALTER TABLE',
                $statement,
                $executedSql,
                true,
            ];
        }

        return [
            'CREATE TABLE',
            'CREATE TABLE',
            $statement,
            $statement,
            false,
        ];
    }

    private static function statementShouldBeLogged(string $command, int|false $affected): bool
    {
        $command = strtoupper($command);

        if (in_array($command, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) {
            return (int) $affected > 0;
        }

        return true;
    }

    private static function extractCommand(string $statement): string
    {
        $statement = ltrim($statement);

        if (preg_match('/^([A-Z]+)\s+([A-Z]+)/i', $statement, $matches)) {
            $first = strtoupper($matches[1]);
            $second = strtoupper($matches[2]);

            if ($first === 'CREATE' && $second === 'TABLE') {
                return 'CREATE TABLE';
            }

            if ($first === 'ALTER' && $second === 'TABLE') {
                return 'ALTER TABLE';
            }

            if ($first === 'INSERT' && $second === 'INTO') {
                return 'INSERT';
            }

            if ($first === 'DELETE' && $second === 'FROM') {
                return 'DELETE';
            }
        }

        if (preg_match('/^([A-Z]+)/i', $statement, $matches)) {
            return strtoupper($matches[1]);
        }

        return 'SQL';
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
            return [
                'files' => [],
                'statements' => [],
            ];
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return [
                'files' => [],
                'statements' => [],
            ];
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return [
                'files' => [],
                'statements' => [],
            ];
        }

        if (!isset($decoded['files']) || !is_array($decoded['files'])) {
            $decoded['files'] = [];
        }

        if (!isset($decoded['statements']) || !is_array($decoded['statements'])) {
            $decoded['statements'] = [];
        }

        return $decoded;
    }

    private static function writeState(array $state): void
    {
        @file_put_contents(
            self::stateFile(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private static function saveErrorsEnabled(): bool
    {
        if (!function_exists('fwConfig')) {
            return true;
        }

        return (bool) fwConfig('save_errors', true);
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

    private static function logChange(string $file, string $targetCommand, string $executedCommand, string $targetSql, string $executedSql): void
    {
        if (!self::saveErrorsEnabled()) {
            return;
        }

        $lines = [];
        $lines[] = '[' . date('Y-m-d H:i:s') . '] DB SCHEME CHANGE';
        $lines[] = 'file: ' . $file;
        $lines[] = 'target_command: ' . $targetCommand;
        $lines[] = 'executed_command: ' . $executedCommand;
        $lines[] = 'target_sql: ' . self::normalizeLogValue($targetSql);
        $lines[] = 'executed_sql: ' . self::normalizeLogValue($executedSql);
        $lines[] = str_repeat('-', 80);

        @file_put_contents(
            self::logFile(),
            implode(PHP_EOL, $lines) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function logError(string $file, string $targetCommand, string $targetSql, Throwable $e): void
    {
        if (!self::saveErrorsEnabled()) {
            return;
        }

        $lines = [];
        $lines[] = '[' . date('Y-m-d H:i:s') . '] DB SCHEME ERROR';
        $lines[] = 'file: ' . $file;
        $lines[] = 'target_command: ' . $targetCommand;
        $lines[] = 'executed_command: ERROR';
        $lines[] = 'target_sql: ' . self::normalizeLogValue($targetSql);
        $lines[] = 'executed_sql: ERROR';
        $lines[] = 'error: ' . $e->getMessage();
        $lines[] = 'code: ' . (string) $e->getCode();
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