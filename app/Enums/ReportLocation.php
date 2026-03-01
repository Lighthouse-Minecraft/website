<?php

namespace App\Enums;

enum ReportLocation: string
{
    case Minecraft = 'minecraft';
    case DiscordText = 'discord_text';
    case DiscordVoice = 'discord_voice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Minecraft => 'Minecraft',
            self::DiscordText => 'Discord Text',
            self::DiscordVoice => 'Discord Voice',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Minecraft => 'green',
            self::DiscordText => 'indigo',
            self::DiscordVoice => 'purple',
            self::Other => 'zinc',
        };
    }
}
