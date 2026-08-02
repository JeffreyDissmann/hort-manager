<?php

declare(strict_types=1);

// Schließzeiten: Tage, an denen der Hort komplett geschlossen ist.
return [
    'title' => 'Schließzeiten',
    'header' => 'Schließzeiten',
    'intro' => 'Tage, an denen der Hort geschlossen ist – Ferien, Brückentage, Fortbildungen. An diesen Tagen gibt es keinen Abholplan und kein Tagesprogramm.',

    'add_heading' => 'Schließzeit eintragen',
    'edit_heading' => 'Schließzeit bearbeiten',
    'add' => 'Eintragen',

    'name' => 'Name',
    'name_placeholder' => 'z. B. Sommerferien',
    'from' => 'Von',
    'to' => 'Bis',
    'note' => 'Notiz (optional)',
    'note_placeholder' => 'z. B. Hort öffnet wieder am 1. September',

    'upcoming_heading' => 'Kommende Schließzeiten',
    'none' => 'Aktuell sind keine Schließzeiten eingetragen.',
    // The frontend $t() has no pluralisation — pick the key in the component.
    'day_one' => '1 Tag',
    'day_many' => ':count Tage',
    'delete_confirm' => 'Wirklich löschen?',
    'show_past' => 'Vergangene anzeigen (:count)',
    'hide_past' => 'Vergangene ausblenden',
];
