<?php

namespace App\Services;

class VaultSession
{
    private const SESSION_KEY = 'vault_unlocked_at';

    public function unlock(): void
    {
        session([self::SESSION_KEY => now()->timestamp]);
    }

    public function isUnlocked(): bool
    {
        $unlockedAt = session(self::SESSION_KEY);

        if ($unlockedAt === null) {
            return false;
        }

        $ttl = (int) config('vault.session_ttl_minutes', 30);

        return now()->timestamp - $unlockedAt < $ttl * 60;
    }

    public function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
