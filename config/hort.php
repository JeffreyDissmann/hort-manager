<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | Lives in the `settings` table now, not here: see Setting::RetentionMonths
    | and „Datenpflege" under Verwaltung, where an admin can change it without a
    | deploy. hort:prune-old-data reads it nightly and deletes day boards, day
    | programs, excursions and absences older than that. Children, guardians, the
    | Stammplan and accounts are never pruned.
    |
    */

];
