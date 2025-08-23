<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders the join discord image component', function () {
    $html = Blade::render("@include('components.join-discord-image')");
    expect($html)->toContain('alt="Join Discord"');
    expect($html)->toContain('discord-banner.png');
    expect($html)->toContain('copyAndJoinDiscord');
});

it('shows the toast message for copying', function () {
    $html = Blade::render("@include('components.join-discord-image')");
    expect($html)->toContain('Minecraft server IP copied!');
});
