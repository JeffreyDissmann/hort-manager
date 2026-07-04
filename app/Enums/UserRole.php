<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Parent = 'parent';

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.user_role.'.$this->value);
    }
}
