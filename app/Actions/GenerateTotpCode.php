<?php

namespace App\Actions;

use App\Models\Credential;
use Lorisleiva\Actions\Concerns\AsAction;
use OTPHP\InternalClock;
use OTPHP\TOTP;

class GenerateTotpCode
{
    use AsAction;

    /**
     * Generate the current TOTP code for a credential.
     *
     * @return array{code: string, seconds_remaining: int}
     */
    public function handle(Credential $credential): array
    {
        $rawSecret = $credential->totp_secret;

        if (blank($rawSecret)) {
            throw new \RuntimeException('TOTP secret is missing for this credential.');
        }

        $totp = TOTP::createFromSecret($rawSecret, new InternalClock);

        return [
            'code' => $totp->now(),
            'seconds_remaining' => $totp->expiresIn(),
        ];
    }
}
