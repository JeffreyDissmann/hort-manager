<?php

declare(strict_types=1);

// „Datenpflege" — die Lücken in den eigenen Daten, die jemand schließen kann.
return [
    'title' => 'Datenpflege',
    'intro' => 'Der Stand von heute. Die Zahl führt jeweils dorthin, wo sich das erledigen lässt.',
    'clear' => 'Alles gepflegt – nichts offen.',

    'check' => [
        'children_without_plan' => 'Kinder ohne Stammplan',
        'children_without_guardian' => 'Kinder ohne verknüpftes Elternteil',
        'guardians_without_slack' => 'Eltern ohne Slack-Konto',
        'orphaned_accounts' => 'Konten ohne Kind und ohne Rolle',
        'failed_jobs' => 'Fehlgeschlagene Hintergrund-Jobs',
        'open_excursion_answers' => 'Offene Antworten zu kommenden Ausflügen',
    ],

    'inventory_title' => 'Was gespeichert ist',
    'inventory_intro' => 'Der Hort-Manager legt pro Kind und Tag eine Zeile an – und löscht bisher nichts davon. Hier steht, wie viel sich angesammelt hat und wie weit es zurückreicht.',
    'inventory_what' => 'Daten',
    'inventory_count' => 'Einträge',
    'inventory_oldest' => 'ältester',
    'record' => [
        'departures' => 'Abholungen',
        'absences' => 'Abwesenheiten',
        'activity_log' => 'Protokoll-Einträge',
    ],
    'children_count' => 'Kinder (ehemalige)',
    'users_count' => 'Konten',
    'database_size' => 'Datenbank',
];
