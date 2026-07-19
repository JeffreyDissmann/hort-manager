<?php

declare(strict_types=1);

// Buchhaltung (admin-only accounting module) — page headers, labels and hints.
return [
    'title' => 'Buchhaltung',

    'accounts' => [
        'title' => 'Konten',
        'intro' => 'Bank- und Bar-Konten. Der Saldo ergibt sich aus dem Anfangssaldo plus allen bestätigten Buchungen.',
        'new' => 'Neues Konto',
        'edit' => 'Konto bearbeiten',
        'name' => 'Name',
        'name_placeholder' => 'z. B. Konto, Bar-Kasse',
        'iban' => 'IBAN',
        'iban_invalid' => 'Bitte eine gültige IBAN eingeben (z. B. DE89 3704 0044 0532 0130 00).',
        'opening_balance' => 'Anfangssaldo (€)',
        'opening_balance_date' => 'Stichtag Anfangssaldo',
        'active' => 'Aktiv',
        'active_hint' => 'Inaktive Konten bleiben erhalten, tauchen aber nicht mehr in der Buchungsauswahl auf.',
        'balance' => 'Saldo',
        'bookings_count' => 'Buchungen',
        'empty' => 'Noch keine Konten angelegt.',
        'delete_confirm' => 'Konto „:name“ wirklich löschen?',
    ],

    'categories' => [
        'title' => 'Kategorien',
        'intro' => 'Buchungskategorien als Baum. Die Richtung (Einnahme/Ausgabe) legt die oberste Ebene fest und vererbt sich an alle Unterkategorien.',
        'income' => 'Einnahmen',
        'expense' => 'Ausgaben',
        'new_root' => 'Neue Hauptkategorie',
        'add_child' => 'Unterkategorie',
        'name_placeholder' => 'Name der Kategorie',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'bookings_count' => 'Buchungen',
        'empty' => 'Noch keine Kategorien.',
        'delete_confirm' => 'Kategorie „:name“ wirklich löschen?',
        'rename' => 'Umbenennen',
        'deactivate' => 'Deaktivieren',
        'activate' => 'Aktivieren',
    ],
];
