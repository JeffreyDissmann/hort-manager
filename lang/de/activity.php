<?php

declare(strict_types=1);

// The admin activity log (audit trail) page. Descriptions are composed in the
// frontend from these pieces so they follow the de/en toggle.
return [
    'title' => 'Protokoll',
    'header' => 'Protokoll',
    'intro' => 'Wer hat wann was geändert – nur für Admins sichtbar.',
    'empty' => 'Noch keine Einträge.',
    'system' => 'System',
    'newer' => 'Neuer',
    'older' => 'Älter',
    'bool_true' => 'ja',
    'bool_false' => 'nein',

    // Event badge labels.
    'events' => [
        'created' => 'Angelegt',
        'updated' => 'Geändert',
        'deleted' => 'Entfernt',
        'picked_up' => 'Abgeholt',
        'sent_home' => 'Nach Hause',
        'present' => 'Zurückgesetzt',
        'adjusted' => 'Tagesplan',
        'reset' => 'Zurückgesetzt',
        'rsvp_yes' => 'Zusage',
        'rsvp_no' => 'Absage',
        'guardians' => 'Betreuer',
    ],

    // Subject nouns (shown for create/update/delete entries).
    'subjects' => [
        'child' => 'Kind',
        'weekly_schedule' => 'Stammplan',
        'absence' => 'Abwesenheit',
        'excursion' => 'Ausflug',
        'daily_program' => 'Tagesprogramm',
        'user' => 'Benutzer',
    ],

    // Changed-field names (shown in the diff).
    'fields' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'role' => 'Rolle',
        'is_admin' => 'Admin',
        'note' => 'Notiz',
        'date_of_birth' => 'Geburtsdatum',
        'date' => 'Datum',
        'weekday' => 'Wochentag',
        'planned_time' => 'Uhrzeit',
        'method' => 'Art',
        'companion' => 'Begleitkind',
        'time_qualifier' => 'Zeitangabe',
        'comment' => 'Kommentar',
        'lunch' => 'Mittagessen',
        'activity' => 'Aktivität',
        'homework_start' => 'Hausaufgaben von',
        'homework_end' => 'Hausaufgaben bis',
        'homework_none' => 'Keine Hausaufgaben',
        'depart_at' => 'Abfahrt',
        'return_at' => 'Rückkehr',
        'rsvp_deadline' => 'Antwortfrist',
    ],

    // Known enum values shown in the diff (method, qualifier, role, reason).
    'values' => [
        'picked_up' => 'wird abgeholt',
        'sent_home' => 'geht allein',
        'with_child' => 'geht mit einem Kind mit',
        'by' => 'bis',
        'at' => 'genau um',
        'from' => 'ab',
        'sick' => 'krank',
        'away' => 'kommt nicht',
        'staff' => 'Erzieher:in',
        'parent' => 'Elternteil',
    ],
];
