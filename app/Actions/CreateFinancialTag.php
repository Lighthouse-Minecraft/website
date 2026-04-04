<?php

namespace App\Actions;

use App\Models\FinancialTag;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateFinancialTag
{
    use AsAction;

    public function handle(string $name, User $createdBy): FinancialTag
    {
        return FinancialTag::create([
            'name' => $name,
            'created_by' => $createdBy->id,
            'is_archived' => false,
        ]);
    }
}
