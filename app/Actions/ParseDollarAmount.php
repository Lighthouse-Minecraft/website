<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class ParseDollarAmount
{
    use AsAction;

    /**
     * Parse a dollar string to integer cents.
     * Accepts: "10", "10.0", "10.00", "0.99"
     * Returns: integer cents (10 → 1000, 0.99 → 99)
     *
     * @throws \InvalidArgumentException for non-numeric or negative input
     */
    public function handle(string $input): int
    {
        $input = trim($input);

        // Only digits with an optional decimal point (up to 2 decimal places)
        if (! preg_match('/^\d+(\.\d{0,2})?$/', $input)) {
            throw new \InvalidArgumentException("Invalid dollar amount: {$input}");
        }

        return (int) round((float) $input * 100);
    }
}
