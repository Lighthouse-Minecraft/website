<?php

namespace App\Actions;

use App\Models\Credential;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCredential
{
    use AsAction;

    public function handle(Credential $credential, User $updater, array $data): Credential
    {
        $updates = ['updated_by' => $updater->id];

        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }

        if (array_key_exists('website_url', $data)) {
            $updates['website_url'] = $data['website_url'];
        }

        if (isset($data['username'])) {
            $updates['username'] = $data['username'];
        }

        if (array_key_exists('email', $data)) {
            $updates['email'] = $data['email'];
        }

        if (array_key_exists('totp_secret', $data)) {
            $updates['totp_secret'] = $data['totp_secret'];
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = $data['notes'];
        }

        if (array_key_exists('recovery_codes', $data)) {
            $updates['recovery_codes'] = $data['recovery_codes'];
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $updates['password'] = $data['password'];
            $updates['needs_password_change'] = false;
        }

        $credential->update($updates);

        RecordActivity::run($credential, 'credential_updated', "Credential \"{$credential->name}\" updated by {$updater->name}.");
        RecordCredentialAccess::run($credential, $updater, 'updated');

        return $credential->fresh();
    }
}
