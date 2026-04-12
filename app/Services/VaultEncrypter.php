<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

class VaultEncrypter
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $key = config('vault.key');

        if (empty($key)) {
            throw new \RuntimeException(
                'VAULT_KEY is not set. Add VAULT_KEY to your .env file. '.
                'Generate a value with: php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"'
            );
        }

        $this->encrypter = new Encrypter(base64_decode($key), 'AES-256-CBC');
    }

    public function encrypt(string $value): string
    {
        return $this->encrypter->encrypt($value);
    }

    public function decrypt(string $payload): string
    {
        return $this->encrypter->decrypt($payload);
    }
}
