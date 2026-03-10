<?php

namespace App\Actions;

use App\Services\DocumentationService;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveDocumentPage
{
    use AsAction;

    public function handle(string $relativePath, array $meta, string $body): void
    {
        if (! app()->isLocal()) {
            abort(403, 'Editing is only available in local environment.');
        }

        $service = app(DocumentationService::class);

        if (! $service->isValidDocsPath($relativePath)) {
            abort(403, 'Invalid file path.');
        }

        $service->savePage($relativePath, $meta, $body);
    }
}
