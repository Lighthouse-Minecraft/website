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

    public function flags(): HasMany
    {
        return $this->hasMany(MessageFlag::class);
    }

    public function addParticipant(User $user): void
    {
        $this->participants()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

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
}
