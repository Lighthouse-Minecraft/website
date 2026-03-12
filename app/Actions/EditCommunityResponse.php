<?php

namespace App\Actions;

use App\Models\CommunityResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class EditCommunityResponse
{
    use AsAction;

    public function handle(
        CommunityResponse $response,
        User $editor,
        string $body,
        ?UploadedFile $newImage = null,
        bool $removeImage = false,
    ): CommunityResponse {
        if (! $response->isEditable()) {
            throw new \RuntimeException('This response can no longer be edited.');
        }

        $response->body = $body;

        if ($removeImage && $response->image_path) {
            Storage::disk(config('filesystems.public'))->delete($response->image_path);
            $response->image_path = null;
        } elseif ($newImage) {
            if ($response->image_path) {
                Storage::disk(config('filesystems.public'))->delete($response->image_path);
            }
            $response->image_path = $newImage->store('community-stories', config('filesystems.public'));
        }

        $response->save();

        RecordActivity::run($response, 'community_response_edited', "Response #{$response->id} edited by {$editor->name}.");

        return $response;
    }
}
