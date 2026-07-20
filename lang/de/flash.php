<?php

declare(strict_types=1);

// Server-side flash messages (session 'status'), shown as a banner after an action.
return [
    'min_one_admin' => 'Es muss mindestens eine:n Administrator:in geben.',
    'user_updated' => ':name aktualisiert.',
    'cannot_delete_self' => 'Du kannst dich nicht selbst löschen.',
    'user_deleted' => ':name wurde gelöscht.',
    'users_synced' => ':count Benutzer aus Slack synchronisiert.',

    'absence_reported' => ':name als „:reason“ gemeldet.',
    'absence_cleared' => 'Abwesenheit für :name aufgehoben.',

    'child_created' => 'Kind angelegt. Jetzt den Stammplan festlegen.',
    'schedule_saved' => 'Stammplan für :name gespeichert.',
    'child_deleted' => ':name wurde gelöscht.',

    'plan_updated' => 'Plan für :name aktualisiert.',
    'day_reset' => ':name: Tag auf Standard zurückgesetzt.',
    'companion_answered' => 'Antwort für :name gespeichert.',

    'excursion_created' => 'Ausflug „:name“ angelegt. Die Eltern wurden zur Abstimmung eingeladen.',
    'excursion_saved' => 'Ausflug „:name“ gespeichert.',
    'excursion_deleted' => 'Ausflug „:name“ gelöscht.',
    'rsvp_saved' => 'Antwort für :name gespeichert.',

    'program_saved' => 'Programm gespeichert.',
    'homework_defaults_saved' => 'Standard-Hausaufgabenzeiten gespeichert.',

    'account_created' => 'Konto „:name“ angelegt.',
    'account_updated' => 'Konto „:name“ gespeichert.',
    'account_deleted' => 'Konto „:name“ gelöscht.',
    'account_has_bookings' => 'Konto „:name“ hat Buchungen und kann nicht gelöscht werden. Deaktiviere es stattdessen.',

    'category_created' => 'Kategorie angelegt.',
    'category_updated' => 'Kategorie gespeichert.',
    'category_deleted' => 'Kategorie „:name“ gelöscht.',
    'category_has_bookings' => 'Kategorie „:name“ (oder eine Unterkategorie) hat Buchungen und kann nicht gelöscht werden. Deaktiviere sie stattdessen.',

    'booking_created' => 'Buchung angelegt.',
    'booking_updated' => 'Buchung gespeichert.',
    'booking_deleted' => 'Buchung gelöscht.',
    'bookings_reanalysing' => ':count Buchungen werden von der KI neu analysiert.',
    'bookings_confirmed' => ':count Buchungen bestätigt.',
    'ai_disabled' => 'Die KI-Analyse ist aktuell deaktiviert.',
    'transfer_created' => 'Umbuchung angelegt.',

    'import_done' => ':imported Buchungen importiert, :duplicates Duplikate übersprungen. Bitte prüfen.',
    'import_saved' => 'Gespeichert. Es sind noch Entwürfe zu prüfen.',
    'import_complete' => 'Alle importierten Buchungen bestätigt.',
];
