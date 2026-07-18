<?php

declare(strict_types=1);

// Notification settings page: the per-category × per-channel opt-out matrix.
return [
    'title' => 'Notifications',
    'description' => 'Choose what you want to be notified about — separately for Slack and Push (to this device).',
    'saved' => 'Notification settings saved.',

    'channel_slack' => 'Slack',
    'channel_push' => 'Push',

    'matrix_title' => 'What and how',
    'matrix_description' => 'Turn individual notifications on or off per channel. Everything is on by default.',

    'slack_disabled_hint' => 'Link your Slack account (via “Sign in with Slack”) to receive Slack notifications.',
    'push_hint' => 'Push only reaches you if you have enabled Push on this device above.',

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
        'weekly_digest' => [
            'label' => 'Weekly overview',
            'help' => "Mondays: the week's food and activities plus a short summary for your child.",
        ],
    ],
];
