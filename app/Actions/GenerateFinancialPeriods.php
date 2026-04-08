<?php

namespace App\Actions;

use App\Models\FinancialPeriod;
use App\Models\SiteConfig;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateFinancialPeriods
{
    use AsAction;

    /**
     * Generate the 12 monthly fiscal periods for a given fiscal year if they do not already exist.
     *
     * Fiscal year convention: FY N starts on month $startMonth of year N-1 (if $startMonth > 1)
     * or month 1 of year N (if $startMonth == 1) and runs for 12 months.
     * Example: FY 2026 with startMonth=10 → Oct 2025 – Sep 2026.
     *
     * Calling this multiple times is safe; `firstOrCreate` prevents duplicates.
     */
    public function handle(int $fyYear, int $startMonth): void
    {
        $startCalYear = ($startMonth === 1) ? $fyYear : $fyYear - 1;
        $startDate = Carbon::create($startCalYear, $startMonth, 1);

        for ($i = 0; $i < 12; $i++) {
            $periodStart = $startDate->copy()->addMonths($i);
            $periodEnd = $periodStart->copy()->endOfMonth();

            FinancialPeriod::firstOrCreate(
                ['fiscal_year' => $fyYear, 'month_number' => $periodStart->month],
                [
                    'name' => $periodStart->format('F Y'),
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'open',
                ]
            );
        }
    }

    /**
     * Resolve the current fiscal year and start month from SiteConfig, then generate periods.
     */
    public static function generateForCurrentFY(): void
    {
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $now = now();
        $fyYear = ($now->month >= $startMonth) ? $now->year + 1 : $now->year;

        static::run($fyYear, $startMonth);
    }
}
