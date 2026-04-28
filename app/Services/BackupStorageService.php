<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class BackupStorageService
{
    private const PREFIX = 'backups/';

    /**
     * Upload a local backup file to S3 and return the S3 key.
     */
    public function upload(string $localPath): string
    {
        $filename = basename($localPath);
        $key = self::PREFIX.$filename;

        Storage::disk('s3')->put($key, file_get_contents($localPath));

        return $key;
    }

    /**
     * List all S3 backup keys sorted by filename timestamp (newest first).
     *
     * @return string[]
     */
    public function list(): array
    {
        $files = Storage::disk('s3')->files(self::PREFIX);

        // Keep only .sql.gz backup files
        $files = array_filter($files, fn ($f) => str_ends_with($f, '.sql.gz'));

        usort($files, function ($a, $b) {
            return $this->parseTimestamp($b) <=> $this->parseTimestamp($a);
        });

        return array_values($files);
    }

    /**
     * Download an S3 backup to a temp file and return the local path.
     * The caller is responsible for deleting the temp file.
     */
    public function download(string $key): string
    {
        $tmpPath = sys_get_temp_dir().'/s3_backup_'.uniqid().'.sql.gz';

        file_put_contents($tmpPath, Storage::disk('s3')->get($key));

        return $tmpPath;
    }

    /**
     * Delete an S3 backup by key.
     */
    public function delete(string $key): void
    {
        Storage::disk('s3')->delete($key);
    }

    /**
     * Return true if S3 is configured with credentials and reachable.
     */
    public function isConfigured(): bool
    {
        $cfg = config('filesystems.disks.s3', []);

        return ! empty($cfg['key']) && ! empty($cfg['bucket']);
    }

    public function isConnected(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            Storage::disk('s3')->files(self::PREFIX);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * List S3 backups with metadata (filename, size, date, key).
     *
     * @return array<int, array{key: string, filename: string, size: string, date: string}>
     */
    public function listWithMetadata(): array
    {
        return array_map(function (string $key) {
            return [
                'key' => $key,
                'filename' => basename($key),
                'size' => $this->formatBytes(Storage::disk('s3')->size($key)),
                'date' => Carbon::createFromTimestamp(Storage::disk('s3')->lastModified($key))->format('Y-m-d H:i'),
            ];
        }, $this->list());
    }

    /**
     * Parse the timestamp embedded in a backup filename.
     * Filename format: backups/backup_YYYY-MM-DD_HH-MM-SS_<type>.sql.gz
     */
    public function parseTimestamp(string $key): Carbon
    {
        preg_match('/backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $key, $m);

        if (empty($m[1])) {
            return Carbon::createFromTimestamp(0);
        }

        return Carbon::createFromFormat('Y-m-d_H-i-s', $m[1]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }
}
