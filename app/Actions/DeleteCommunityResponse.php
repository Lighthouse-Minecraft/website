<?php

namespace App\Actions;

use App\Models\CommunityResponse;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCommunityResponse
{
    use AsAction;

    public function handle(CommunityResponse $response): void
    {
        $imagePath = $response->image_path;

        $response->delete();

        RecordActivity::run($response, 'community_response_deleted', "Response #{$response->id} deleted.");

        if ($imagePath) {
            Storage::disk(config('filesystems.public_disk'))->delete($imagePath);
        }
    }
}
