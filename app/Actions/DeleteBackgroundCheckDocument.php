<?php

namespace App\Actions;

use App\Models\BackgroundCheckDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBackgroundCheckDocument
{
    use AsAction;

    public function handle(BackgroundCheckDocument $document, User $deletedBy): void
    {
        $check = $document->backgroundCheck;

        if ($check->isLocked()) {
            throw new \InvalidArgumentException('Cannot delete documents from a locked background check.');
        }

        Storage::disk(config('filesystems.public_disk'))->delete($document->path);

        $document->delete();

        RecordActivity::run(
            $check,
            'background_check_document_deleted',
            "Document \"{$document->original_filename}\" deleted from background check by {$deletedBy->name}.",
            $deletedBy,
        );
    }
}
