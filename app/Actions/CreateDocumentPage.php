<?php

namespace App\Actions;

use App\Services\DocumentationService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDocumentPage
{
    use AsAction;

    public function handle(string $relativeDir, string $filename, array $meta, string $body): string
    {
        if (! app()->isLocal()) {
            abort(403);
        }

        $service = app(DocumentationService::class);
        $service->createPage($relativeDir, $filename, $meta, $body);

        return $relativeDir.'/'.$filename;
    }
}
