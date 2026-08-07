<?php

declare(strict_types=1);

// „Statistik" — die Admin-Auswertung des Hort-Alltags.
return [
    'title' => 'Statistik',

    'range' => [
        'quarter' => 'Letzte 3 Monate',
        'school-year' => 'Schuljahr',
        'year' => 'Kalenderjahr',
    ],

    'empty' => 'Für diesen Zeitraum gibt es noch nichts zu zeigen.',

    'absences_title' => 'Krank und abwesend',
    'absences_intro' => 'Gemeldete Abwesenheiten pro Monat, getrennt nach Grund – eine Erkältungswelle sieht anders aus als ein paar Arzttermine.',
    'absences_empty' => 'In diesem Zeitraum wurde niemand abgemeldet.',
    'absence_sick' => 'Krank',
    'absence_away' => 'Kommt nicht',

    'pickup_times_title' => 'Wann die Kinder gehen',
    'pickup_times_intro' => 'Geplante Abholzeiten in halben Stunden – also die Zeiten, für die der Hort besetzt sein muss. Ganz links die Tage, an denen ein Kind krank oder abgemeldet war. Die Linie zeigt, wie viel Prozent nach dieser Uhrzeit noch da sind.',
    'pickup_absent' => 'gar nicht da',
    'pickup_remaining' => 'noch da',
];
