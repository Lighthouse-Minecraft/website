<?php

namespace App\Actions;

use App\Models\FinancialCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateFinancialCategory
{
    use AsAction;

    public function handle(string $name, string $type, ?int $parentId = null): FinancialCategory
    {
        if ($parentId !== null) {
            $parent = FinancialCategory::findOrFail($parentId);

            if ($parent->parent_id !== null) {
                throw new \InvalidArgumentException('Cannot create a subcategory under another subcategory.');
            }
        }

        $maxSort = FinancialCategory::where('parent_id', $parentId)
            ->where('type', $type)
            ->max('sort_order') ?? -1;

        return FinancialCategory::create([
            'name' => $name,
            'type' => $type,
            'parent_id' => $parentId,
            'sort_order' => $maxSort + 1,
            'is_archived' => false,
        ]);
    }
}
