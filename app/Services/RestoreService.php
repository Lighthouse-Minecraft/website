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
        $sql = $this->readGzip($path);

        $result = Process::env(['PGPASSWORD' => $config['password'] ?? ''])
            ->input($sql)
            ->run([
                'psql',
                '-h', $config['host'] ?? 'localhost',
                '-p', (string) ($config['port'] ?? 5432),
                '-U', $config['username'],
                $config['database'],
            ]);

        if (! $result->successful()) {
            throw new \RuntimeException('psql restore failed: '.$result->errorOutput());
        }
    }

    private function restoreMysql(string $path, array $config): void
    {
        $sql = $this->readGzip($path);

        $result = Process::input($sql)->run([
            'mysql',
            '-h', $config['host'] ?? 'localhost',
            '-P', (string) ($config['port'] ?? 3306),
            '-u', $config['username'],
            '-p'.$config['password'],
            $config['database'],
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('mysql restore failed: '.$result->errorOutput());
        }
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

        // Strip SQL comments, then execute each statement individually.
        // PDO::exec() for SQLite only runs the first statement in a multi-statement
        // string, so we must split and execute one at a time.
        $cleanSql = preg_replace('/--[^\n]*/', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $cleanSql))) as $stmt) {
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

    private function buildTargetDsn(string $type, array $config): string
    {
        return match ($type) {
            'pgsql' => "postgresql://{$config['username']}:{$config['password']}@{$config['host']}:{$config['port']}/{$config['database']}",
            'mysql', 'mariadb' => "mysql://{$config['username']}:{$config['password']}@{$config['host']}:{$config['port']}/{$config['database']}",
            default => throw new \RuntimeException("Cannot build DSN for type: {$type}"),
        };
    }
}
