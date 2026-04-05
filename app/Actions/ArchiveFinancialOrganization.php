<?php

namespace App\Actions;

use App\Models\FinancialOrganization;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveFinancialOrganization
{
    use AsAction;

    public function handle(FinancialOrganization $organization): void
    {
        $organization->is_archived = true;
        $organization->save();

        RecordActivity::run($organization, 'archived_financial_organization', 'Archived financial organization.');
    }
}
