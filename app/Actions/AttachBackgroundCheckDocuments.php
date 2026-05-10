<?php

namespace App\Actions;

use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachBackgroundCheckDocuments
{
    use AsAction;

    public function handle(BackgroundCheck $check, array $files, User $uploadedBy): void
    {
        $maxKb = (int) (SiteConfig::getValue('max_background_check_document_size_kb', '10240') ?: 10240);
        if ($maxKb <= 0) {
            $maxKb = 10240;
        }

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            Validator::make(
                ['file' => $file],
                ['file' => "mimes:pdf|max:{$maxKb}"]
            )->validate();
        }

        DB::transaction(function () use ($check, $files, $uploadedBy): void {
            foreach ($files as $file) {
                /** @var UploadedFile $file */
                $path = $file->store("background-checks/{$check->id}", config('filesystems.public_disk'));

                BackgroundCheckDocument::create([
                    'background_check_id' => $check->id,
                    'path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'uploaded_by_user_id' => $uploadedBy->id,
                ]);

                RecordActivity::run(
                    $check,
                    'background_check_document_attached',
                    "Document \"{$file->getClientOriginalName()}\" attached to background check by {$uploadedBy->name}.",
                    $uploadedBy,
                );
            }
        });
    }
}
