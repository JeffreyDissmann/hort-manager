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
        'away' => 'Kommt nicht',
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
        'with_child' => 'Geht mit einem anderen Kind mit',
    ],
    'time_qualifier' => [
        'by' => 'Bis zu der Uhrzeit',
        'at' => 'Genau zur Uhrzeit',
        'from' => 'Ab der Uhrzeit (oder später)',
    ],
    'time_qualifier_prefix' => [
        'by' => 'bis',
        'at' => 'um',
        'from' => 'ab',
    ],
    'category_direction' => [
        'income' => 'Einnahme',
        'expense' => 'Ausgabe',
    ],
    'booking_kind' => [
        'income' => 'Einnahme',
        'expense' => 'Ausgabe',
        'transfer' => 'Umbuchung',
    ],
    'booking_status' => [
        'draft' => 'Entwurf',
        'suggested' => 'KI-Vorschlag',
        'confirmed' => 'Bestätigt',
    ],
    'suggestion_confidence' => [
        0 => 'Unsicher',
        1 => 'Mittel',
        2 => 'Sicher',
    ],
];
