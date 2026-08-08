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
    'inventory_intro' => 'The Hort-Manager writes one row per child per day — and deletes none of it so far. This is how much has piled up and how far back it goes.',
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
];
