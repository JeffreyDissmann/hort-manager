<?php

declare(strict_types=1);

// Ausflug (excursion) screens: planning, the parent RSVP poll and history.
return [
    'heading' => 'Ausflüge',
    'poll_title' => 'Ausflüge – Abstimmung',
    'plan_title' => 'Ausflug planen',
    'edit_heading' => 'Ausflug bearbeiten',
    'edit_title' => ':name bearbeiten',

    'intro' => 'Plane einen Ausflug und lege eine Frist fest – alle Eltern werden automatisch gefragt, ob ihr Kind mitkommt. Am Ausflugstag erscheinen die zugesagten Kinder im Tagesboard.',

    'upcoming_heading' => 'Anstehende Ausflüge',
    'past_heading' => 'Vergangene Ausflüge',
    'past_hint' => 'Bereits stattgefundene Ausflüge mit den Kindern, die dabei waren.',
    'none_planned' => 'Aktuell sind keine Ausflüge geplant.',
    'none_planned_create' => 'Aktuell sind keine Ausflüge geplant. Lege oben den ersten an.',

    'depart' => 'Abfahrt',
    'return' => 'Rückkehr',
    'time_range' => ':from–:to Uhr',
    'time_from' => 'ab :time Uhr',

    'poll_closed' => 'Abstimmung beendet',
    'poll_until' => 'Abstimmung bis :date',
    'deadline_today' => 'Heute ist der letzte Tag zum Antworten',
    'deadline_tomorrow' => 'Bitte bis morgen antworten',
    'deadline_days' => 'Bitte bis :date antworten (noch :n Tage)',

    'answer_yes' => 'Kommt mit',
    'answer_no' => 'Nicht dabei',

    'status_open' => 'noch offen',
    'status_confirmed' => 'zugesagt',
    'status_declined' => 'abgesagt',
    'open' => 'offen',
    'joined' => 'dabei',
    'not_joined' => 'nicht dabei',
    'no_response' => 'keine Rückmeldung',

    'joining_count' => ':count dabei',
    'joining_count_past' => ':count waren dabei',
    'pending_count' => 'offen :count',
    'view' => 'Ansehen',

    'responses_heading' => 'Rückmeldungen',
    'responses_hint' => 'Status der Eltern-Abstimmung. Du kannst bei Bedarf selbst eintragen.',

    'delete_confirm' => 'Ausflug „:name“ wirklich löschen?',
    'delete_aria' => 'Ausflug löschen',

    'field_name' => 'Name des Ausflugs',
    'field_name_placeholder' => 'z. B. Zoo-Ausflug',
    'field_date' => 'Datum',
    'next_friday' => 'Nächster Freitag · :date',
    'field_deadline' => 'Rückmeldung bis',
    'field_deadline_hint' => 'Bis zu diesem Tag können die Eltern Rückmeldung geben. Standard: 3 Tage vor dem Ausflug.',
    'field_note' => 'Notiz (optional)',
    'field_note_placeholder' => 'z. B. Brotzeit und feste Schuhe mitbringen',

    'after_save_before' => 'Nach dem Speichern werden ',
    'after_save_strong' => 'alle Eltern',
    'after_save_after' => ' gefragt, ob ihr Kind mitkommt.',

    'weekdays' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
];
