<?php

namespace App\Services;

use App\Models\SiteConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class BackupService
{
    private bool $skipOffline = false;

    public function setSkipOffline(bool $skip): self
    {
        $this->skipOffline = $skip;

        return $this;
    }

    public function create(): string
    {
        $driver = config('database.default');
        $config = config("database.connections.{$driver}");

        $backupsDir = storage_path('app/backups');
        if (! is_dir($backupsDir)) {
            mkdir($backupsDir, 0755, true);
        }

        $dbType = match ($driver) {
            'pgsql' => 'pgsql',
            'mysql', 'mariadb' => 'mysql',
            'sqlite' => 'sqlite',
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}_{$dbType}.sql.gz";
        $path = "{$backupsDir}/{$filename}";

        $offline = SiteConfig::getValue('backup.offline_during_backup', 'false') === 'true';
        $shouldGoOffline = ! $this->skipOffline && $offline;

        if ($shouldGoOffline) {
            Artisan::call('down');
        }

        try {
            $sql = $this->dump($driver, $config);

            $gz = gzopen($path, 'wb9');
            gzwrite($gz, $sql);
            gzclose($gz);
        } catch (\Throwable $e) {
            if ($shouldGoOffline) {
                Artisan::call('up');
            }
            throw $e;
        }

        if ($shouldGoOffline) {
            Artisan::call('up');
        }

        return $path;
    }

    private function dump(string $driver, array $config): string
    {
        return match ($driver) {
            'pgsql' => $this->dumpPostgres($config),
            'mysql', 'mariadb' => $this->dumpMysql($config),
            'sqlite' => $this->dumpSqlite(),
            default => throw new \RuntimeException("Unsupported driver: {$driver}"),
        };
    }

    private function dumpPostgres(array $config): string
    {
        $result = Process::env(['PGPASSWORD' => $config['password'] ?? ''])
            ->run([
                'pg_dump',
                '-h', $config['host'] ?? 'localhost',
                '-p', (string) ($config['port'] ?? 5432),
                '-U', $config['username'],
                $config['database'],
            ]);

        if (! $result->successful()) {
            throw new \RuntimeException('pg_dump failed: '.$result->errorOutput());
        }

        return $result->output();
    }

    private function dumpMysql(array $config): string
    {
        $result = Process::run([
            'mysqldump',
            '-h', $config['host'] ?? 'localhost',
            '-P', (string) ($config['port'] ?? 3306),
            '-u', $config['username'],
            '-p'.$config['password'],
            $config['database'],
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('mysqldump failed: '.$result->errorOutput());
        }

        return $result->output();
    }

    private function dumpSqlite(): string
    {
        $pdo = DB::connection()->getPdo();

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $sql = "-- SQLite dump\n\n";

        foreach ($tables as $table) {
            if ($table['sql']) {
                $sql .= $table['sql'].";\n\n";
            }

            $rows = $pdo->query("SELECT * FROM \"{$table['name']}\"")->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $columns = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys($row)));
                $values = implode(', ', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($row)
                ));
                $sql .= "INSERT INTO \"{$table['name']}\" ({$columns}) VALUES ({$values});\n";
            }

            $sql .= "\n";
        }

        return $sql;
    }
}
