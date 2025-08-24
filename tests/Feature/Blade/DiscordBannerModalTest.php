<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders the Discord banner modal component', function () {
    $html = Blade::render("@include('components.discord-banner-modal')");
    expect($html)->toContain('Join LighthouseMC');
    expect($html)->toContain('mc-banner.png');
    // SVG may not render in test, fallback to label
    expect($html)->toContain('Add Bedrock server');
    expect($html)->toContain('mc-java-bedrock.png');
});

it('shows Discord invite button', function () {
    $html = Blade::render("@include('components.discord-banner-modal')");
    expect($html)->toContain('Join our Discord');
    expect($html)->toContain('openDiscordInvite');
});
