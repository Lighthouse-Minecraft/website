<?php

namespace App\Models;

use App\Services\VaultEncrypter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credential extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website_url',
        'username',
        'email',
        'password',
        'totp_secret',
        'notes',
        'recovery_codes',
        'needs_password_change',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'needs_password_change' => 'boolean',
        ];
    }

    // === Encrypted field accessors/mutators ===

    public function getUsernameAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = app(VaultEncrypter::class)->encrypt($value);
    }

    public function getEmailAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value !== null
            ? app(VaultEncrypter::class)->encrypt($value)
            : null;
    }

    public function getPasswordAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = app(VaultEncrypter::class)->encrypt($value);
    }

    public function getTotpSecretAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setTotpSecretAttribute(?string $value): void
    {
        $this->attributes['totp_secret'] = $value !== null
            ? app(VaultEncrypter::class)->encrypt($value)
            : null;
    }

    public function getNotesAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value !== null
            ? app(VaultEncrypter::class)->encrypt($value)
            : null;
    }

    public function getRecoveryCodesAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(VaultEncrypter::class)->decrypt($value);
    }

    public function setRecoveryCodesAttribute(?string $value): void
    {
        $this->attributes['recovery_codes'] = $value !== null
            ? app(VaultEncrypter::class)->encrypt($value)
            : null;
    }

    // === Relationships ===

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function staffPositions(): BelongsToMany
    {
        return $this->belongsToMany(StaffPosition::class, 'credential_staff_position')
            ->withTimestamps();
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CredentialAccessLog::class);
    }
}
