<?php

namespace App\Services;

use App\Models\SiteConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class RestoreService
{
    public function restore(string $path): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Restore is blocked in the production environment. '.
                'To perform an emergency restore, set APP_ENV=backup-restore in .env and restart PHP-FPM and the queue worker.'
            );
        }

        if (! file_exists($path)) {
            throw new \RuntimeException("Backup file not found: {$path}");
        }

        $filename = basename($path);
        $sourceType = $this->detectSourceType($filename);

        $targetDriver = config('database.default');
        $targetConfig = config("database.connections.{$targetDriver}");

        $targetType = match ($targetConfig['driver'] ?? $targetDriver) {
            'pgsql' => 'pgsql',
            'mysql', 'mariadb' => 'mysql',
            'sqlite' => 'sqlite',
            default => throw new \RuntimeException("Unsupported target database driver: {$targetDriver}"),
        };

        $offline = SiteConfig::getValue('backup.offline_during_restore', 'true') === 'true';

        if ($offline) {
            Artisan::call('down');
        }

        try {
            if ($sourceType === $targetType) {
                $this->restoreSameType($path, $targetType, $targetConfig);
            } else {
                $this->restoreCrossType($path, $sourceType, $targetType, $targetConfig);
            }
        } catch (\Throwable $e) {
            if ($offline) {
                Artisan::call('up');
            }
            throw $e;
        }

        if ($offline) {
            Artisan::call('up');
        }
    }

    private function detectSourceType(string $filename): string
    {
        if (str_contains($filename, '_pgsql.sql.gz')) {
            return 'pgsql';
        }

        if (str_contains($filename, '_mysql.sql.gz')) {
            return 'mysql';
        }

        if (str_contains($filename, '_sqlite.sql.gz')) {
            return 'sqlite';
        }

        throw new \RuntimeException("Cannot detect source database type from filename: {$filename}");
    }

    private function restoreSameType(string $path, string $type, array $config): void
    {
        match ($type) {
            'pgsql' => $this->restorePostgres($path, $config),
            'mysql' => $this->restoreMysql($path, $config),
            'sqlite' => $this->restoreSqlite($path),
            default => throw new \RuntimeException("Unsupported type: {$type}"),
        };
    }

    private function restorePostgres(string $path, array $config): void
    {
        $envPrefix = 'PGPASSWORD='.escapeshellarg($config['password'] ?? '');
        $connArgs = ' -h '.escapeshellarg($config['host'] ?? 'localhost')
            .' -p '.escapeshellarg((string) ($config['port'] ?? 5432))
            .' -U '.escapeshellarg($config['username'])
            .' '.escapeshellarg($config['database']);

        // Drop all objects in the public schema without requiring schema ownership.
        // DROP SCHEMA CASCADE requires the user to own the schema (PostgreSQL 15+),
        // but dropping individual tables/sequences/views works with table ownership.
        $wipeSql = <<<'SQL'
DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
        EXECUTE 'DROP TABLE IF EXISTS public.' || quote_ident(r.tablename) || ' CASCADE';
    END LOOP;
    FOR r IN (SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = 'public') LOOP
        EXECUTE 'DROP SEQUENCE IF EXISTS public.' || quote_ident(r.sequence_name) || ' CASCADE';
    END LOOP;
    FOR r IN (SELECT table_name FROM information_schema.views WHERE table_schema = 'public') LOOP
        EXECUTE 'DROP VIEW IF EXISTS public.' || quote_ident(r.table_name) || ' CASCADE';
    END LOOP;
END $$;
SQL;
        $tempFile = tempnam(sys_get_temp_dir(), 'wipe_pg_');
        file_put_contents($tempFile, $wipeSql);

        try {
            $wipeResult = Process::run(
                $envPrefix.' psql'.$connArgs.' -f '.escapeshellarg($tempFile)
            );
        } finally {
            @unlink($tempFile);
        }

        if (! $wipeResult->successful()) {
            throw new \RuntimeException('Failed to wipe PostgreSQL schema before restore: '.$wipeResult->errorOutput());
        }

        $cmd = $envPrefix.' psql'.$connArgs;

        $handle = popen($cmd, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open psql process');
        }

        $gz = gzopen($path, 'rb');
        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 65536);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($handle, $chunk);
                }
            }
        } finally {
            gzclose($gz);
            $exitCode = pclose($handle);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("psql restore failed with exit code {$exitCode}");
        }
    }

    private function restoreMysql(string $path, array $config): void
    {
        $envPrefix = 'MYSQL_PWD='.escapeshellarg($config['password'] ?? '');
        $connArgs = ' -h '.escapeshellarg($config['host'] ?? 'localhost')
            .' -P '.escapeshellarg((string) ($config['port'] ?? 3306))
            .' -u '.escapeshellarg($config['username'])
            .' '.escapeshellarg($config['database']);

        // Wipe all tables so the restore starts against an empty database.
        $db = escapeshellarg($config['database']);
        $wipeResult = Process::run(
            $envPrefix.' mysql'.$connArgs
            .' -e "SET FOREIGN_KEY_CHECKS=0; DROP DATABASE '.$db.'; CREATE DATABASE '.$db.'; SET FOREIGN_KEY_CHECKS=1;"'
        );

        if (! $wipeResult->successful()) {
            throw new \RuntimeException('Failed to wipe MySQL database before restore: '.$wipeResult->errorOutput());
        }

        $cmd = $envPrefix.' mysql'.$connArgs;

        $handle = popen($cmd, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open mysql process');
        }

        $gz = gzopen($path, 'rb');
        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 65536);
                if ($chunk !== false && $chunk !== '') {
                    fwrite($handle, $chunk);
                }
            }
        } finally {
            gzclose($gz);
            $exitCode = pclose($handle);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("mysql restore failed with exit code {$exitCode}");
        }
    }

    private function readGzip(string $path): string
    {
        $gz = gzopen($path, 'rb');
        $sql = '';
        while (! gzeof($gz)) {
            $sql .= gzread($gz, 65536);
        }
        gzclose($gz);

        return $sql;
    }

    private function restoreSqlite(string $path): void
    {
        $sql = $this->readGzip($path);
        $pdo = DB::connection()->getPdo();

        $pdo->exec('PRAGMA foreign_keys = OFF');

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
        }

        // PDO::exec() for SQLite only runs the first statement in a multi-statement
        // string, so we must split and execute one at a time. Use a character-level
        // parser so semicolons inside string literals are not treated as delimiters.
        foreach ($this->splitSqlStatements($sql) as $stmt) {
            $pdo->exec($stmt);
        }

        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function restoreCrossType(string $path, string $sourceType, string $targetType, array $config): void
    {
        $check = Process::run(['which', 'pgloader']);

        if (! $check->successful() || empty(trim($check->output()))) {
            throw new \RuntimeException(
                "Cross-type restore from {$sourceType} to {$targetType} requires pgloader, ".
                'which was not found in $PATH. '.
                'Please pre-convert the dump file locally using pgloader (https://pgloader.io) '.
                'or pg2mysql (https://github.com/dolthub/pg2mysql), then upload the converted '.
                'file and restore from that.'
            );
        }

        $tempPath = sys_get_temp_dir().'/restore_'.uniqid().'.sql';
        file_put_contents($tempPath, $this->readGzip($path));

        try {
            $dsn = $this->buildTargetDsn($targetType, $config);
            $result = Process::run(['pgloader', $tempPath, $dsn]);

            if (! $result->successful()) {
                throw new \RuntimeException('pgloader failed: '.$result->errorOutput());
            }
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Split a SQL string into individual statements, respecting single- and
     * double-quoted literals so semicolons inside values are not treated as
     * statement terminators. Also skips -- line comments.
     *
     * @return string[]
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $char = $sql[$i];

            // Line comment: skip to end of line.
            if ($char === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Quoted string: consume until closing quote, handling doubled-quote escapes.
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $current .= $char;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $current .= $c;
                    $i++;
                    if ($c === $quote) {
                        // Doubled quote is an escape inside the literal.
                        if ($i < $len && $sql[$i] === $quote) {
                            $current .= $sql[$i];
                            $i++;
                        } else {
                            break;
                        }
                    }
                }

                continue;
            }

            // Statement terminator.
            if ($char === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    private function buildTargetDsn(string $type, array $config): string
    {
        $username = rawurlencode((string) ($config['username'] ?? ''));
        $password = rawurlencode((string) ($config['password'] ?? ''));
        $database = rawurlencode((string) ($config['database'] ?? ''));
        $host = $config['host'] ?? 'localhost';
        $port = (string) ($config['port'] ?? ($type === 'pgsql' ? 5432 : 3306));

        return match ($type) {
            'pgsql' => "postgresql://{$username}:{$password}@{$host}:{$port}/{$database}",
            'mysql', 'mariadb' => "mysql://{$username}:{$password}@{$host}:{$port}/{$database}",
            default => throw new \RuntimeException("Cannot build DSN for type: {$type}"),
        };
    }
}
