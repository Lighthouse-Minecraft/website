<?php

namespace App\Actions;

use App\Models\SiteConfig;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateRulesHeaderFooter
{
    use AsAction;

    public function handle(string $header, string $footer): void
    {
        SiteConfig::setValue('rules_header', $header);
        SiteConfig::setValue('rules_footer', $footer);
    }
}
