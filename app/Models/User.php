<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EmailDigestFrequency;
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
        'timezone',
        'pushover_key',
        'email_digest_frequency',
        'notification_preferences',
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
            'email_digest_frequency' => EmailDigestFrequency::class,
            'rules_accepted_at' => 'datetime',
            'promoted_at' => 'datetime',
            'last_prayed_at' => 'datetime',
            'last_notification_read_at' => 'datetime',
            'last_ticket_digest_sent_at' => 'datetime',
            'pushover_count_reset_at' => 'datetime',
            'notification_preferences' => 'array',
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

    public function prayerCountries()
    {
        return $this->belongsToMany(PrayerCountry::class)->withPivot('year')->withTimestamps();
    }

    public function canSendPushover(): bool
    {
        if (! $this->pushover_key) {
            return false;
        }

        // Reset counter if it's a new month
        if (! $this->pushover_count_reset_at || $this->pushover_count_reset_at->lt(now()->startOfMonth())) {
            $this->update([
                'pushover_monthly_count' => 0,
                'pushover_count_reset_at' => now()->startOfMonth(),
            ]);
        }

        return $this->pushover_monthly_count < 10000;
    }

    /**
     * Increments the stored `pushover_monthly_count` attribute by one.
     */
    public function incrementPushoverCount(): void
    {
        $this->increment('pushover_monthly_count');
    }

    /**
     * Determines whether the user has actionable support tickets.
     *
     * Actionable means an unassigned open ticket or a ticket assigned to the user that has unread messages.
     * The result is cached for 60 minutes and may be refreshed in the background if the cached value is older than 30 minutes.
     *
     * @return bool `true` if the user has actionable tickets, `false` otherwise.
     */
    public function hasActionableTickets(): bool
    {
        $cacheKey = "user.{$this->id}.actionable_tickets";
        $timestampKey = "user.{$this->id}.actionable_tickets.timestamp";

        // Check if cache needs background refresh (older than 30 minutes)
        $timestamp = \Illuminate\Support\Facades\Cache::get($timestampKey);
        if ($timestamp && now()->diffInMinutes($timestamp) > 30) {
            // Dispatch background refresh
            \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));
            $userId = $this->id;
            dispatch(static function () use ($cacheKey, $userId) {
                $user = User::find($userId);
                if ($user) {
                    $result = $user->calculateActionableTickets();
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addMinutes(60));
                }
            })->afterResponse();
        }

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addMinutes(60),
            function () use ($timestampKey) {
                \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));

                return $this->calculateActionableTickets();
            }
        );
    }

    /**
     * Determine whether any actionable support tickets exist for this user given their visibility permissions.
     *
     * Considers two types of actionable tickets: unassigned tickets with an open status, and tickets assigned to
     * the user that are not closed and contain unread messages for the user. The check respects the user's visibility
     * (their own tickets, department tickets if allowed, and flagged tickets if allowed).
     *
     * @return bool `true` if at least one actionable ticket exists, `false` otherwise.
     */
    protected function calculateActionableTickets(): bool
    {
        $baseQuery = Thread::query();

        // Apply visibility filters
        if (! $this->can('viewAll', Thread::class)) {
            $baseQuery->where(function ($q) {
                // User's tickets (participant or assigned)
                $q->whereHas('participants', fn ($sq) => $sq->where('user_id', $this->id))
                    ->orWhere('assigned_to_user_id', $this->id);

                // Department tickets
                if ($this->can('viewDepartment', Thread::class) && $this->staff_department) {
                    $q->orWhere('department', $this->staff_department);
                }

                // Flagged tickets
                if ($this->can('viewFlagged', Thread::class)) {
                    $q->orWhere('is_flagged', true);
                }
            });
        }

        // Check for actionable tickets
        return $baseQuery
            ->where(function ($q) {
                // Unassigned tickets that are open
                $q->where(function ($sq) {
                    $sq->whereNull('assigned_to_user_id')
                        ->where('status', \App\Enums\ThreadStatus::Open);
                })
                // OR tickets assigned to me with unread messages
                    ->orWhere(function ($sq) {
                        $sq->where('assigned_to_user_id', $this->id)
                            ->where('status', '!=', \App\Enums\ThreadStatus::Closed)
                            ->where(function ($usq) {
                                // Consider unread if: no participant row exists OR participant row exists but is unread
                                $usq->whereNotExists(function ($nesq) {
                                    $nesq->select(\Illuminate\Support\Facades\DB::raw(1))
                                        ->from('thread_participants')
                                        ->whereColumn('thread_participants.thread_id', 'threads.id')
                                        ->where('thread_participants.user_id', $this->id);
                                })
                                    ->orWhereExists(function ($esq) {
                                        $esq->select(\Illuminate\Support\Facades\DB::raw(1))
                                            ->from('thread_participants')
                                            ->whereColumn('thread_participants.thread_id', 'threads.id')
                                            ->where('thread_participants.user_id', $this->id)
                                            ->where(function ($rsq) {
                                                $rsq->whereNull('thread_participants.last_read_at')
                                                    ->orWhereColumn('threads.last_message_at', '>', 'thread_participants.last_read_at');
                                            });
                                    });
                            });
                    });
            })
            ->exists();
    }

    /**
     * Check if the user has unread messages in tickets where they are a participant.
     *
     * This is used to determine whether to show a red badge on the "My Tickets" navigation item.
     * It only checks tickets where the user is a participant (not all actionable tickets).
     * The value is cached for 60 minutes; if the cached result is older than 30 minutes a background refresh is scheduled.
     *
     * @return bool `true` if the user has unread participant tickets, `false` otherwise.
     */
    public function hasUnreadParticipantTickets(): bool
    {
        $cacheKey = "user.{$this->id}.unread_participant_tickets";
        $timestampKey = "user.{$this->id}.unread_participant_tickets.timestamp";

        // Check if cache needs background refresh (older than 30 minutes)
        $timestamp = \Illuminate\Support\Facades\Cache::get($timestampKey);
        if ($timestamp && now()->diffInMinutes($timestamp) > 30) {
            // Dispatch background refresh
            \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));
            $userId = $this->id;
            dispatch(static function () use ($cacheKey, $userId) {
                $user = User::find($userId);
                if ($user) {
                    $result = $user->calculateUnreadParticipantTickets();
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addMinutes(60));
                }
            })->afterResponse();
        }

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addMinutes(60),
            function () use ($timestampKey) {
                \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));

                return $this->calculateUnreadParticipantTickets();
            }
        );
    }

    /**
     * Calculate if the user has unread messages in tickets where they are a participant.
     *
     * @return bool `true` if the user has unread participant tickets, `false` otherwise.
     */
    protected function calculateUnreadParticipantTickets(): bool
    {
        return Thread::whereHas('participants', fn ($sq) => $sq->where('user_id', $this->id))
            ->where('status', '!=', \App\Enums\ThreadStatus::Closed)
            ->where(function ($q) {
                // Consider unread if: no participant row exists OR participant row exists but is unread
                $q->whereNotExists(function ($nesq) {
                    $nesq->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('thread_participants')
                        ->whereColumn('thread_participants.thread_id', 'threads.id')
                        ->where('thread_participants.user_id', $this->id);
                })
                    ->orWhereExists(function ($esq) {
                        $esq->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('thread_participants')
                            ->whereColumn('thread_participants.thread_id', 'threads.id')
                            ->where('thread_participants.user_id', $this->id)
                            ->where(function ($rsq) {
                                $rsq->whereNull('thread_participants.last_read_at')
                                    ->orWhereColumn('threads.last_message_at', '>', 'thread_participants.last_read_at');
                            });
                    });
            })
            ->exists();
    }

    /**
     * Retrieve the number of open tickets visible to the user.
     *
     * The value is cached for 60 minutes; if the cached result is older than 30 minutes a background refresh is scheduled while the cached value is returned immediately.
     *
     * @return int The count of open tickets visible to this user.
     */
    public function openTicketsCount(): int
    {
        $cacheKey = "user.{$this->id}.open_tickets_count";
        $timestampKey = "user.{$this->id}.open_tickets_count.timestamp";

        // Check if cache needs background refresh (older than 30 minutes)
        $timestamp = \Illuminate\Support\Facades\Cache::get($timestampKey);
        if ($timestamp && now()->diffInMinutes($timestamp) > 30) {
            // Dispatch background refresh
            \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));
            $userId = $this->id;
            dispatch(static function () use ($cacheKey, $userId) {
                $user = User::find($userId);
                if ($user) {
                    $result = $user->calculateOpenTicketsCount();
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addMinutes(60));
                }
            })->afterResponse();
        }

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addMinutes(60),
            function () use ($timestampKey) {
                \Illuminate\Support\Facades\Cache::put($timestampKey, now(), now()->addMinutes(60));

                return $this->calculateOpenTicketsCount();
            }
        );
    }

    /**
     * Count participant tickets that need attention.
     *
     * Counts tickets where the user is a participant that are:
     * - Non-closed (Open, Pending, Resolved), OR
     * - Closed with unread messages
     *
     * This provides an accurate count for the sidebar badge.
     *
     * @return int The number of participant tickets needing attention.
     */
    protected function calculateOpenTicketsCount(): int
    {
        return Thread::whereHas('participants', fn ($sq) => $sq->where('user_id', $this->id))
            ->where(function ($q) {
                // Non-closed tickets
                $q->where('status', '!=', \App\Enums\ThreadStatus::Closed)
                    // OR closed tickets with unread messages
                    ->orWhere(function ($sq) {
                        $sq->where('status', \App\Enums\ThreadStatus::Closed)
                            ->whereExists(function ($esq) {
                                $esq->select(\Illuminate\Support\Facades\DB::raw(1))
                                    ->from('thread_participants')
                                    ->whereColumn('thread_participants.thread_id', 'threads.id')
                                    ->where('thread_participants.user_id', $this->id)
                                    ->where(function ($rsq) {
                                        $rsq->whereNull('thread_participants.last_read_at')
                                            ->orWhereColumn('threads.last_message_at', '>', 'thread_participants.last_read_at');
                                    });
                            });
                    });
            })
            ->count();
    }

    /**
     * Clear all ticket-related caches for this user.
     *
     * Should be called when ticket state changes that might affect cached values
     * (e.g., new messages, status changes, assignment changes).
     */
    public function clearTicketCaches(): void
    {
        $cacheKeys = [
            "user.{$this->id}.actionable_tickets",
            "user.{$this->id}.actionable_tickets.timestamp",
            "user.{$this->id}.unread_participant_tickets",
            "user.{$this->id}.unread_participant_tickets.timestamp",
            "user.{$this->id}.open_tickets_count",
            "user.{$this->id}.open_tickets_count.timestamp",
        ];

        foreach ($cacheKeys as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }
}
