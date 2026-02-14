<?php

namespace App\Models;

use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Enums\ThreadType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'subtype',
        'department',
        'subject',
        'status',
        'created_by_user_id',
        'assigned_to_user_id',
        'is_flagged',
        'has_open_flags',
        'last_message_at',
    ];

    protected $casts = [
        'type' => ThreadType::class,
        'subtype' => ThreadSubtype::class,
        'department' => StaffDepartment::class,
        'status' => ThreadStatus::class,
        'is_flagged' => 'boolean',
        'has_open_flags' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class);
    }

    /**
     * Get the message flags associated with the thread.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany The has-many relation for MessageFlag models.
     */
    public function flags(): HasMany
    {
        return $this->hasMany(MessageFlag::class);
    }

    /**
     * Ensure the given user is a participant of the thread.
     *
     * Creates a participant record for the user if one does not already exist.
     * When a new participant is created, the `is_viewer` flag is set according to `$isViewer`.
     * If a participant already exists as a viewer and $isViewer is false, promotes them to a full participant.
     *
     * @param  User  $user  The user to ensure is a participant.
     * @param  bool  $isViewer  Whether to mark the participant as a viewer when creating; defaults to false.
     */
    public function addParticipant(User $user, bool $isViewer = false): void
    {
        $participant = $this->participants()->firstOrCreate(
            ['user_id' => $user->id],
            ['is_viewer' => $isViewer]
        );

        // Promote viewer to participant if needed
        if (! $isViewer && $participant->is_viewer) {
            $participant->update(['is_viewer' => false]);
        }
    }

    /**
     * Adds the given user to the thread as a viewer.
     *
     * @param  User  $user  The user to add as a viewer participant.
     */
    public function addViewer(User $user): void
    {
        $this->addParticipant($user, isViewer: true);
    }

    /**
     * Determine whether the given user may view this thread.
     *
     * A user may view the thread if any of the following are true:
     * - the user has the `viewAll` ability for threads;
     * - the user has the `viewFlagged` ability and the thread is flagged;
     * - the user has the `viewDepartment` ability and the thread's department matches the user's staff department;
     * - the user is a participant of the thread.
     *
     * @param  \App\Models\User  $user  The user to check visibility for.
     * @return bool `true` if the user may view the thread, `false` otherwise.
     */
    public function isVisibleTo(User $user): bool
    {
        // Check if user can view all tickets
        if ($user->can('viewAll', Thread::class)) {
            return true;
        }

        // Check if user can view flagged tickets and this is flagged
        if ($user->can('viewFlagged', Thread::class) && $this->is_flagged) {
            return true;
        }

        // Check if user can view their department's tickets
        if ($user->can('viewDepartment', Thread::class) && $this->department === $user->staff_department) {
            return true;
        }

        // Check if user is a participant
        if ($this->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the thread has unread messages for the given user.
     *
     * If the user has no participant record for this thread or has never read it, the thread is considered unread.
     *
     * @param  User  $user  The user to check unread status for.
     * @return bool `true` if the thread has unread messages for the user, `false` otherwise.
     */
    public function isUnreadFor(User $user): bool
    {
        $participant = $this->participants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant || ! $participant->last_read_at) {
            return true; // Never read
        }

        return $this->last_message_at > $participant->last_read_at;
    }
}
