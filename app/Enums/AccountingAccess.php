<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A user's access to the Buchhaltung (accounting) module — an axis independent of
 * the Erzieher/Parent role and of admin. Hierarchical: `Write` implies `Read`.
 */
enum AccountingAccess: string
{
    case None = 'none';
    case Read = 'read';
    case Write = 'write';

    /** May view accounting pages (read-only or read-write). */
    public function canRead(): bool
    {
        return $this !== self::None;
    }

    /** May create/edit/delete in accounting. */
    public function canWrite(): bool
    {
        return $this === self::Write;
    }

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.accounting_access.'.$this->value);
    }
}
