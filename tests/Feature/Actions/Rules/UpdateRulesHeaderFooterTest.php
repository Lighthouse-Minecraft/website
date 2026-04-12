<?php

declare(strict_types=1);

use App\Actions\UpdateRulesHeaderFooter;
use App\Models\SiteConfig;

uses()->group('rules', 'actions');

it('saves rules_header to SiteConfig', function () {
    UpdateRulesHeaderFooter::run('# New Header', 'Footer text.');

    expect(SiteConfig::getValue('rules_header'))->toBe('# New Header');
});

it('saves rules_footer to SiteConfig', function () {
    UpdateRulesHeaderFooter::run('Header text.', 'New footer content.');

    expect(SiteConfig::getValue('rules_footer'))->toBe('New footer content.');
});

it('overwrites existing header and footer values', function () {
    SiteConfig::setValue('rules_header', 'Old header');
    SiteConfig::setValue('rules_footer', 'Old footer');

    UpdateRulesHeaderFooter::run('New header', 'New footer');

    expect(SiteConfig::getValue('rules_header'))->toBe('New header')
        ->and(SiteConfig::getValue('rules_footer'))->toBe('New footer');
});
