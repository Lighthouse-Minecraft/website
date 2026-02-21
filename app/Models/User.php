<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EmailDigestFrequency;
use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'in_brig',
        'brig_reason',
        'brig_expires_at',
        'brig_timer_notified',
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
            'last_login_at' => 'datetime',
            'last_ticket_digest_sent_at' => 'datetime',
            'pushover_count_reset_at' => 'datetime',
            'notification_preferences' => 'array',
            'in_brig' => 'boolean',
            'brig_expires_at' => 'datetime',
            'brig_timer_notified' => 'boolean',
        ];
    }

    public function isInBrig(): bool
    {
        return (bool) $this->in_brig;
    }

    public function brigTimerExpired(): bool
    {
        return $this->brig_expires_at === null || now()->gte($this->brig_expires_at);
    }

    public function canAppeal(): bool
    {
        return $this->in_brig && $this->brigTimerExpired();
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

    public function minecraftAccounts(): HasMany
    {
        return $this->hasMany(MinecraftAccount::class);
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
     * Get all ticket-related counts in a single query.
     *
     * Fetches minimal ticket data once and calculates all counts from it.
     * This is much more efficient than running separate COUNT queries.
     *
     * @return array{badge: int, my-open: int, my-closed: int, open: int, closed: int, assigned-to-me: int, unassigned: int, flagged: int, has-unread: bool}
     */
    public function ticketCounts(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "user.{$this->id}.ticket_counts",
            now()->addMinutes(5),
            function () {
                // Fetch minimal ticket data in ONE query
                $query = Thread::query()
                    ->select('id', 'status', 'department', 'assigned_to_user_id', 'has_open_flags', 'is_flagged', 'last_message_at')
                    ->with(['participants' => function ($q) {
                        $q->where('user_id', $this->id)
                            ->select('thread_id', 'user_id', 'is_viewer', 'last_read_at');
                    }]);

                // Apply visibility filters
                if (! $this->can('viewAll', Thread::class)) {
                    $query->where(function ($q) {
                        // User's participant tickets
                        $q->whereHas('participants', fn ($psq) => $psq->where('user_id', $this->id)->where('is_viewer', false));

                        // Department tickets (if staff)
                        if ($this->can('viewDepartment', Thread::class) && $this->staff_department) {
                            $q->orWhere('department', $this->staff_department);
                        }

                        // Flagged tickets (if has permission)
                        if ($this->can('viewFlagged', Thread::class)) {
                            $q->orWhere('is_flagged', true);
                        }

                        // Assigned tickets
                        $q->orWhere('assigned_to_user_id', $this->id);
                    });
                }

                $tickets = $query->get();

                // Calculate all counts from this ONE result set
                $myParticipantTickets = $tickets->filter(fn ($t) => $t->participants->where('is_viewer', false)->isNotEmpty());

                // Badge count: non-closed participant tickets + closed participant tickets with unread messages
                $badgeCount = $myParticipantTickets
                    ->filter(function ($t) {
                        if ($t->status !== \App\Enums\ThreadStatus::Closed) {
                            return true; // All non-closed participant tickets
                        }

                        // For closed tickets, only count if unread
                        $participant = $t->participants->first();

                        return ! $participant || ! $participant->last_read_at || $t->last_message_at > $participant->last_read_at;
                    })
                    ->count();

                return [
                    'badge' => $badgeCount,
                    'my-open' => $myParticipantTickets->where('status', '!=', \App\Enums\ThreadStatus::Closed)->count(),
                    'my-closed' => $myParticipantTickets->where('status', \App\Enums\ThreadStatus::Closed)->count(),
                    'my-closed-unread' => $myParticipantTickets
                        ->where('status', \App\Enums\ThreadStatus::Closed)
                        ->filter(function ($t) {
                            $participant = $t->participants->first();

                            return ! $participant || ! $participant->last_read_at || $t->last_message_at > $participant->last_read_at;
                        })
                        ->count(),
                    'my-open-unread' => $myParticipantTickets
                        ->where('status', '!=', \App\Enums\ThreadStatus::Closed)
                        ->filter(function ($t) {
                            $participant = $t->participants->first();

                            return ! $participant || ! $participant->last_read_at || $t->last_message_at > $participant->last_read_at;
                        })
                        ->count(),
                    'open' => $tickets->where('status', '!=', \App\Enums\ThreadStatus::Closed)->count(),
                    'closed' => $tickets->where('status', \App\Enums\ThreadStatus::Closed)->count(),
                    'assigned-to-me' => $tickets->where('assigned_to_user_id', $this->id)->count(),
                    'unassigned' => $tickets
                        ->whereNull('assigned_to_user_id')
                        ->where('status', '!=', \App\Enums\ThreadStatus::Closed)
                        ->count(),
                    'flagged' => $tickets->where('has_open_flags', true)->count(),
                    'has-unread' => $tickets->filter(function ($t) {
                        $participant = $t->participants->first();

                        return $participant && $participant->is_viewer === false && (! $participant->last_read_at || $t->last_message_at > $participant->last_read_at);
                    })->isNotEmpty(),
                ];
            }
        );
    }

    /**
     * Clear all ticket-related caches for this user.
     *
     * Should be called when ticket state changes that might affect cached values
     * (e.g., new messages, status changes, assignment changes).
     */
    public function clearTicketCaches(): void
    {
        \Illuminate\Support\Facades\Cache::forget("user.{$this->id}.ticket_counts");
    }
}
