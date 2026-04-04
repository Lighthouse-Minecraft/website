<?php

namespace App\Actions;

use App\Models\FinancialCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderFinancialCategory
{
    use AsAction;

    public function handle(FinancialCategory $category, int $newSortOrder): void
    {
        $category->sort_order = $newSortOrder;
        $category->save();
    }
}
