<?php

declare(strict_types=1);

// Ferien & Schließzeiten: die beiden Hort-weiten Zeiträume — geschlossen oder
// Ferienbetreuung. Beide werden hier angelegt und einzeln bearbeitet.
return [
    'title' => 'Ferien & Schließzeiten',
    'header' => 'Ferien & Schließzeiten',
    'intro' => 'Zeiträume, in denen kein normaler Hort-Alltag ist: Tage, an denen der Hort geschlossen ist – Ferien, Brückentage, Fortbildungen – und Ferienbetreuung, für die sich Familien tageweise anmelden.',

    'add_heading' => 'Neuen Zeitraum eintragen',
    'edit_heading' => 'Zeitraum bearbeiten',
    'add' => 'Eintragen',
    'open' => 'Öffnen',
    'back_to_list' => 'Alle Zeiträume',

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
