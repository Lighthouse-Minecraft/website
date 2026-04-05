<?php

namespace App\Actions;

use App\Models\FinancialOrganization;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateFinancialOrganization
{
    use AsAction;

    public function handle(string $name, User $createdBy): FinancialOrganization
    {
        $organization = FinancialOrganization::create([
            'name' => $name,
            'created_by' => $createdBy->id,
            'is_archived' => false,
        ]);

        RecordActivity::run($organization, 'created_financial_organization', 'Created financial organization.');

        return $organization;
    }
}
