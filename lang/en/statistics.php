<?php

declare(strict_types=1);

// „Statistik" — the admin view of how the Hort actually runs.
return [
    'title' => 'Statistics',

    'range' => [
        'quarter' => 'Last 3 months',
        'school-year' => 'School year',
        'year' => 'Calendar year',
    ],

    'empty' => 'Nothing to show for this period yet.',

    'attendance_title' => 'How full the Hort was',
    'attendance_intro' => 'Children per Hort day, averaged over the week — not summed, or a week with a public holiday would look quiet rather than short.',
    'attendance_suffix' => 'children on average',

    'absences_title' => 'Sick and away',
    'absences_intro' => 'Reported absences per month, split by reason — a cold going round looks different from a few appointments.',
    'absences_empty' => 'Nobody was reported absent in this period.',
    'absence_sick' => 'Sick',
    'absence_away' => 'Not coming',

    'pickup_times_title' => 'When the children leave',
    'pickup_times_intro' => 'Planned pickup times in half-hour slots — the times the Hort has to be staffed for. On the far left, the days a child was reported sick or away. The line shows what share is still there after each time.',
    'pickup_times_intro_actual' => 'Actual times — when children were really marked off. Days nobody marked off are missing here; the far-left bucket is again who never came.',
    'basis' => [
        'planned' => 'planned',
        'actual' => 'actual',
    ],
    'pickup_absent' => 'never came',
    'pickup_remaining' => 'still here',
];
