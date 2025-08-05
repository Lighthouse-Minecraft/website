<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rules_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'membership_level' => MembershipLevel::class,
            'staff_rank' => StaffRank::class,
            'staff_department' => StaffDepartment::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has the Admin role.
     */
    public function isAdmin(): bool
    {
        return $this->roles()->get()->contains('name', 'Admin');
    }

    /**
     * Check if the user has the Officer role.
     */
    public function isOfficer(): bool
    {
        return $this->hasRole( 'Officer');
    }

    public function isCrewMember(): bool
    {
        return $this->hasRole( 'Crew Member');
    }

    public function isInCommandDepartment(): bool
    {
        return $this->hasRole( 'Command');
    }

    public function isInChaplainDepartment(): bool
    {
        return $this->hasRole( 'Chaplain');
    }

    public function isInEngineeringDepartment(): bool
    {
        return $this->hasRole( 'Engineering');
    }

    public function isInQuartermasterDepartment(): bool
    {
        return $this->hasRole( 'Quartermaster');
    }

    public function isInStewardDepartment(): bool
    {
        return $this->hasRole( 'Steward');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->get()->contains('name', $roleName);
    }

    public function isAtLeastLevel(MembershipLevel $level): bool
    {
        return $this->membership_level->value >= $level->value;
    }
}
