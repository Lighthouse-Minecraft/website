<?php

namespace App\Actions;

use App\Models\FinancialTag;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveFinancialTag
{
    use AsAction;

    public function handle(FinancialTag $tag): void
    {
        $tag->is_archived = true;
        $tag->save();
    }
}
