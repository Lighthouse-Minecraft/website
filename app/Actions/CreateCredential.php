<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCredential
{
    use AsAction;

    public function handle(User $creator, array $data): Credential
    {
        $credential = Credential::create([
            'name' => $data['name'],
            'website_url' => $data['website_url'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'totp_secret' => $data['totp_secret'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recovery_codes' => $data['recovery_codes'] ?? null,
            'needs_password_change' => false,
            'created_by' => $creator->id,
            'updated_by' => null,
        ]);

        RecordActivity::run($credential, 'credential_created', "Credential \"{$credential->name}\" created by {$creator->name}.");

        return $credential;
    }
}
