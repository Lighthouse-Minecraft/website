<?php

namespace App\Enums;

enum MinecraftAccountType: string
{
    case Java = 'java';
    case Bedrock = 'bedrock';

    public function label(): string
    {
        return match ($this) {
            self::Java => 'Java Edition',
            self::Bedrock => 'Bedrock Edition',
        };
    }
}
