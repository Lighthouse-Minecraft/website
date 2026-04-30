<?php

namespace App\Services;

use App\Models\SiteConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");
        $driver = $config['driver'] ?? $connectionName;

        $backupsDir = storage_path('app/backups');
        if (! is_dir($backupsDir)) {
            mkdir($backupsDir, 0755, true);
        }
        // Ensure the directory is traversable regardless of which OS user
        // (queue worker vs. web server) created it.
        @chmod($backupsDir, 0755);

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
            $this->dump($driver, $config, $path);
            @chmod($path, 0644);
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

    private function dump(string $driver, array $config, string $path): void
    {
        match ($driver) {
            'pgsql' => $this->dumpPostgres($config, $path),
            'mysql', 'mariadb' => $this->dumpMysql($config, $path),
            'sqlite' => $this->dumpSqlite($path),
            default => throw new \RuntimeException("Unsupported driver: {$driver}"),
        };
    }

    private function dumpPostgres(array $config, string $path): void
    {
        $cmd = 'PGPASSWORD='.escapeshellarg($config['password'] ?? '')
            .' pg_dump'
            .' -h '.escapeshellarg($config['host'] ?? 'localhost')
            .' -p '.escapeshellarg((string) ($config['port'] ?? 5432))
            .' -U '.escapeshellarg($config['username'])
            .' '.escapeshellarg($config['database']);

        $handle = popen($cmd, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open pg_dump process');
        }

        $gz = gzopen($path, 'wb9');
        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk !== false && $chunk !== '') {
                    gzwrite($gz, $chunk);
                }
            }
        } finally {
            gzclose($gz);
            $exitCode = pclose($handle);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("pg_dump failed with exit code {$exitCode}");
        }
    }

    private function dumpMysql(array $config, string $path): void
    {
        $cmd = 'MYSQL_PWD='.escapeshellarg($config['password'] ?? '')
            .' mysqldump'
            .' -h '.escapeshellarg($config['host'] ?? 'localhost')
            .' -P '.escapeshellarg((string) ($config['port'] ?? 3306))
            .' -u '.escapeshellarg($config['username'])
            .' '.escapeshellarg($config['database']);

        $handle = popen($cmd, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open mysqldump process');
        }

        $gz = gzopen($path, 'wb9');
        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk !== false && $chunk !== '') {
                    gzwrite($gz, $chunk);
                }
            }
        } finally {
            gzclose($gz);
            $exitCode = pclose($handle);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("mysqldump failed with exit code {$exitCode}");
        }
    }

    private function dumpSqlite(string $path): void
    {
        $pdo = DB::connection()->getPdo();

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $gz = gzopen($path, 'wb9');
        try {
            gzwrite($gz, "-- SQLite dump\n\n");

            foreach ($tables as $table) {
                if ($table['sql']) {
                    gzwrite($gz, $table['sql'].";\n\n");
                }

                $colNames = null;
                $stmt = $pdo->query("SELECT * FROM \"{$table['name']}\"");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($colNames === null) {
                        $colNames = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys($row)));
                    }
                    $values = implode(', ', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    ));
                    gzwrite($gz, "INSERT INTO \"{$table['name']}\" ({$colNames}) VALUES ({$values});\n");
                }

                gzwrite($gz, "\n");
            }
        } finally {
            gzclose($gz);
        }
    }
}
