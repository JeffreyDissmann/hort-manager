<?php

declare(strict_types=1);

// Closures: days on which the Hort is shut completely.
return [
    'title' => 'Closures',
    'header' => 'Closures',
    'intro' => 'Days the Hort is closed – holidays, bridge days, training days. There is no pickup plan and no day program on these days.',

    'add_heading' => 'Add a closure',
    'edit_heading' => 'Edit closure',
    'add' => 'Add',

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
