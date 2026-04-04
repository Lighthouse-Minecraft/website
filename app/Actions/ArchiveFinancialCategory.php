<?php

namespace App\Actions;

use App\Models\FinancialCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveFinancialCategory
{
    use AsAction;

    public function handle(FinancialCategory $category): void
    {
        $category->is_archived = true;
        $category->save();
    }
}
