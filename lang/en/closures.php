<?php

declare(strict_types=1);

// Holidays & closures: the two Hort-wide period types — shut, or holiday care.
// Both are created here and edited on their own page.
return [
    'title' => 'Holidays & closures',
    'header' => 'Holidays & closures',
    'intro' => 'Periods without a normal Hort day: days the Hort is closed – holidays, bridge days, training days – and holiday care, which families sign up for day by day.',

    'add_heading' => 'Add a period',
    'edit_heading' => 'Edit period',
    'add' => 'Add',
    'open' => 'Open',
    'back_to_list' => 'All periods',

    'name' => 'Name',
    'name_placeholder' => 'e.g. Summer holidays',
    'from' => 'From',
    'to' => 'To',
    'note' => 'Note (optional)',
    'note_placeholder' => 'e.g. the Hort reopens on 1 September',

    'upcoming_heading' => 'Upcoming closures',
    'none' => 'No closures are set up at the moment.',
    // The frontend $t() has no pluralisation — pick the key in the component.
    'day_one' => '1 day',
    'day_many' => ':count days',
    'delete_confirm' => 'Really delete?',
    'show_past' => 'Show past (:count)',
    'hide_past' => 'Hide past',
];
