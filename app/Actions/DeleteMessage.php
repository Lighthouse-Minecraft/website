<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteMessage
{
    use AsAction;

    public function handle(Message $message, User $deletedBy): void
    {
        $message->update(['deleted_by' => $deletedBy->id]);
        $message->delete();

        RecordActivity::run($message->thread, 'message_deleted', "Message by {$message->user->name} deleted by {$deletedBy->name}.");
    }
}
