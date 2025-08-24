<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
        'staff_rank',
        'staff_department',
        'staff_title',
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
            'rules_accepted_at' => 'datetime',
            'last_prayed_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->exists && ! is_null($this->id);
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

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->get()->contains('name', $roleName);
    }

    public function isAtLeastLevel(MembershipLevel $level): bool
    {
        return $this->membership_level->value >= $level->value;
    }

    public function isLevel(MembershipLevel $level): bool
    {
        return $this->membership_level == $level;
    }

    public function isAtLeastRank(StaffRank $rank): bool
    {
        return ($this->staff_rank?->value ?? 0) >= $rank->value;
    }

    public function isRank(StaffRank $rank): bool
    {
        return $this->staff_rank == $rank;
    }

    public function isInDepartment(StaffDepartment $department): bool
    {
        return $this->staff_department === $department;
    }

    public function acknowledgedAnnouncements()
    {
        return $this->belongsToMany(Announcement::class)->withTimestamps();
    }

    public function acknowledgedBlogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_author', 'author_id', 'blog_id')->withTimestamps();
    }
  
    public function prayerCountries()
    {
        return $this->belongsToMany(PrayerCountry::class)->withPivot('year')->withTimestamps();
    }
}
