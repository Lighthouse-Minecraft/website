<?php

declare(strict_types=1);

use App\Services\Docs\PageDTO;

uses()->group('docs');

it('converts wiki links without labels to markdown links', function () {
    $body = 'Check out [[books/handbook/minecraft/accounts/joining]] for details.';
    $result = PageDTO::processWikiLinks($body);

    expect($result)->toBe('Check out [Joining](/library/books/handbook/minecraft/accounts/joining) for details.');
});

it('converts wiki links with labels to markdown links', function () {
    $body = 'See [[guides/getting-started/intro|the introduction]] for more info.';
    $result = PageDTO::processWikiLinks($body);

    expect($result)->toBe('See [the introduction](/library/guides/getting-started/intro) for more info.');
});

it('replaces whitelisted config variables with values', function () {
    config(['lighthouse.max_minecraft_accounts' => 5]);

    $body = 'You can link up to **{{config:lighthouse.max_minecraft_accounts}}** accounts.';
    $result = PageDTO::processConfigVariables($body);

    expect($result)->toBe('You can link up to **5** accounts.');
});

it('leaves non-whitelisted config variables unresolved', function () {
    $body = 'Secret: {{config:lighthouse.stripe.donation_pricing_table_key}}';
    $result = PageDTO::processConfigVariables($body);

    expect($result)->toBe('Secret: {{config:lighthouse.stripe.donation_pricing_table_key}}');
});

it('leaves malformed config variables untouched', function () {
    $body = 'This is {{not a config}} and neither is {{config:}}';
    $result = PageDTO::processConfigVariables($body);

    expect($result)->toBe('This is {{not a config}} and neither is {{config:}}');
});
