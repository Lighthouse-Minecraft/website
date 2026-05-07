<?php

namespace App\Services;

class RestoreStatusService
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/restore-status.json');
    }

    public function set(string $status, array $extra = []): void
    {
        $existing = $this->read();
        $data = array_merge($existing, ['status' => $status], $extra);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        if (file_put_contents($this->path, $encoded) === false) {
            throw new \RuntimeException('Failed to write restore status file: '.$this->path);
        }
    }

    public function read(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->path), true);

        return is_array($data) ? $data : [];
    }

    public function clear(): void
    {
        @unlink($this->path);
    }
}
