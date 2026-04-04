<?php

namespace App\Actions;

use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishPeriodReport
{
    use AsAction;

    public function handle(string $monthStart, User $publishedBy): FinancialPeriodReport
    {
        // Reject double-publish
        $existing = FinancialPeriodReport::whereDate('month', $monthStart)->first();
        if ($existing && $existing->isPublished()) {
            throw new \RuntimeException('This month has already been published.');
        }

        // Require at least one transaction
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();
        $count = FinancialTransaction::whereBetween('transacted_at', [$monthStart, $monthEnd])->count();
        if ($count === 0) {
            throw new \RuntimeException('Cannot publish a month with no transactions.');
        }

        return FinancialPeriodReport::updateOrCreate(
            ['month' => $monthStart],
            [
                'published_at' => now(),
                'published_by' => $publishedBy->id,
            ]
        );
    }
}
