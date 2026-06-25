<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Parent = 'parent';

    /** German label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Erzieher:in',
            self::Parent => 'Elternteil',
        };
    }
}
