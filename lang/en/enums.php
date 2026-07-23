<?php

declare(strict_types=1);

// Enum labels, resolved server-side (App\Enums\*::label()) and passed to the UI
// already localised. Keyed by the enum's backing value.
return [
    'user_role' => [
        'staff' => 'Educator',
        'parent' => 'Parent',
    ],
    'absence_reason' => [
        'sick' => 'Sick',
        'away' => 'Not coming',
    ],
    'departure_status' => [
        'present' => 'Still here',
        'picked_up' => 'Picked up',
        'sent_home' => 'Sent home',
        'excursion' => 'Excursion',
    ],
    'departure_method' => [
        'picked_up' => 'Picked up',
        'sent_home' => 'Walks home alone',
        'with_child' => 'Goes with another child',
    ],
    'time_qualifier' => [
        'by' => 'By this time',
        'at' => 'Exactly at this time',
        'from' => 'From this time (or later)',
    ],
    'time_qualifier_prefix' => [
        'by' => 'by',
        'at' => 'at',
        'from' => 'from',
    ],
    'category_direction' => [
        'income' => 'Income',
        'expense' => 'Expense',
    ],
    'booking_kind' => [
        'income' => 'Income',
        'expense' => 'Expense',
        'transfer' => 'Transfer',
    ],
    'booking_status' => [
        'draft' => 'Draft',
        'suggested' => 'AI suggestion',
        'confirmed' => 'Confirmed',
    ],
    'suggestion_confidence' => [
        0 => 'Unsure',
        1 => 'Medium',
        2 => 'Confident',
    ],
];
