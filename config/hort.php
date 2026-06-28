<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | How many weeks of operational data to keep. The hort:prune-old-data
    | command deletes day boards, day programs and excursions older than this.
    | Children, guardians, the Stammplan and accounts are never pruned.
    |
    */

    'retention_weeks' => (int) env('DATA_RETENTION_WEEKS', 4),

];
