<?php

namespace App\Actions;

use App\Models\FinancialCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateFinancialCategory
{
    use AsAction;

    public function handle(FinancialCategory $category, string $name): void
    {
        $category->name = $name;
        $category->save();
    }
}
