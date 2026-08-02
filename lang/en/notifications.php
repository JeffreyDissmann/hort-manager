<?php

declare(strict_types=1);

// Notification settings page: the per-category × per-channel opt-out matrix.
return [
    'title' => 'Notifications',
    'description' => 'Choose what you want to be notified about — separately for Slack and Push (to this device).',
    'saved' => 'Notification settings saved.',

    'channel_slack' => 'Slack',
    'channel_push' => 'Push',

    'push_device_title' => 'Push notifications on this device',
    'push_device_description' => 'Push notifications only reach you on devices where you have enabled them. Turn them on or off for this device here.',
    'push_device_toggle' => 'On this device',

    'matrix_title' => 'What and how',
    'matrix_description' => 'Turn individual notifications on or off per channel. Everything is on by default.',

    'slack_disabled_hint' => 'Link your Slack account (via “Sign in with Slack”) to receive Slack notifications.',
    'push_hint' => 'Push only reaches you if you have enabled Push on this device above.',

    'audiences' => [
        'guardian' => 'As a parent',
        'staff' => 'As staff',
    ],

    'late_change_cutoff_link' => 'Currently from :time – change it under Programm',
    'program_missing_time_link' => 'Currently Mondays at :time – change it under Programm',

    'categories' => [
        'departures' => [
            'label' => 'Departures',
            'help' => 'When your child has been picked up or has left on their own.',
        ],
        'excursions' => [
            'label' => 'Excursions',
            'help' => 'New excursions and reminders to vote.',
        ],
        'companion' => [
            'label' => 'Going home together',
            'help' => 'When a child wants to go home with yours, or that request is answered.',
        ],
        'missing_plan' => [
            'label' => 'Missing weekly plan',
            'help' => 'A reminder when your child still has no weekly plan set up.',
        ],
        'care_registration' => [
            'label' => 'Holiday care',
            'help' => 'A reminder while the holiday-care registration is still open.',
        ],
        'weekly_digest' => [
            'label' => 'Weekly overview',
            'help' => "Mondays: the week's food and activities plus a short summary for your child.",
        ],
        'late_change' => [
            'label' => 'Late changes',
            'help' => 'When parents change something for today after the configured cutoff time.',
        ],
        'program_missing' => [
            'label' => 'Week program missing',
            'help' => 'Mondays, shortly before the weekly overview goes to parents: when a lunch is still missing.',
        ],
    ],
];
