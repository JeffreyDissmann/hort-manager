<?php

declare(strict_types=1);

// „Datenpflege" — the gaps in the Hort's own data that someone can go and close.
return [
    'title' => 'Data upkeep',
    'intro' => 'How things stand today. Each number leads to where it can be sorted out.',
    'clear' => 'All tidy — nothing open.',

    'check' => [
        'children_without_plan' => 'Children with no Stammplan',
        'children_without_guardian' => 'Children with no linked parent',
        'guardians_without_slack' => 'Parents without a Slack account',
        'orphaned_accounts' => 'Accounts with no child and no role',
        'failed_jobs' => 'Failed background jobs',
        'open_excursion_answers' => 'Unanswered invitations to upcoming trips',
    ],

    'inventory_title' => 'What is stored',
    'inventory_intro' => 'The Hort-Manager writes one row per child per day. This is how much has piled up and how far back it goes — the oldest entry is the retention period below.',
    'inventory_what' => 'Data',
    'inventory_count' => 'Records',
    'inventory_oldest' => 'oldest',
    'record' => [
        'departures' => 'Departures',
        'absences' => 'Absences',
        'activity_log' => 'Activity log entries',
    ],
    'children_count' => 'Children (former)',
    'users_count' => 'Accounts',
    'database_size' => 'Database',

    'retention_title' => 'Retention',
    'retention_intro' => 'How long the Hort-Manager keeps departures, programs, trips and absences. Anything older is deleted overnight.',
    'retention_forever' => 'Keep everything',
    'retention_months' => ':count months',
    'retention_cutoff' => 'Everything before :date is deleted.',
    'retention_nothing_deleted' => 'Nothing is deleted.',
    'retention_kept' => 'Children, parent links, Stammpläne and accounts are always kept — only the entries of individual days are deleted.',
];
