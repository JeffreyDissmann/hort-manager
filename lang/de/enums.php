<?php

declare(strict_types=1);

// Enum labels, resolved server-side (App\Enums\*::label()) and passed to the UI
// already localised. Keyed by the enum's backing value.
return [
    'user_role' => [
        'staff' => 'Erzieher:in',
        'parent' => 'Elternteil',
    ],
    'absence_reason' => [
        'sick' => 'Krank',
        'away' => 'Abwesend',
    ],
    'departure_status' => [
        'present' => 'Noch da',
        'picked_up' => 'Abgeholt',
        'sent_home' => 'Nach Hause geschickt',
        'excursion' => 'Ausflug',
    ],
    'departure_method' => [
        'picked_up' => 'Wird abgeholt',
        'sent_home' => 'Geht allein nach Hause',
    ],
];
