<?php

namespace App\Enums;

enum TaskCategory: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Docs = 'docs';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Fitur',
            self::Bug => 'Bug',
            self::Docs => 'Dokumentasi',
            self::Other => 'Lainnya',
        };
    }
}
