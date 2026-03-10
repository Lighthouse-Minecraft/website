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

it('replaces url tags with full site URLs', function () {
    $body = 'Visit the [Staff Page]({{url:/staff}}) to see the team.';
    $result = PageDTO::processSiteUrls($body);

    expect($result)->toBe('Visit the [Staff Page]('.url('/staff').') to see the team.');
});

it('handles url tags with nested paths', function () {
    $body = 'Go to [Settings]({{url:/settings/staff-bio}}) to update your bio.';
    $result = PageDTO::processSiteUrls($body);

    expect($result)->toBe('Go to [Settings]('.url('/settings/staff-bio').') to update your bio.');
});

it('handles url tags with query parameters', function () {
    $body = 'Open the [ACP Users]({{url:/acp?tab=user-manager}}) tab.';
    $result = PageDTO::processSiteUrls($body);

    expect($result)->toBe('Open the [ACP Users]('.url('/acp?tab=user-manager').') tab.');
});

it('leaves malformed url tags untouched', function () {
    $body = 'This {{url:no-slash}} should not match.';
    $result = PageDTO::processSiteUrls($body);

    expect($result)->toBe('This {{url:no-slash}} should not match.');
});
