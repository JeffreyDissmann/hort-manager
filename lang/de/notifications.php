<?php

declare(strict_types=1);

// Notification settings page: the per-category × per-channel opt-out matrix.
return [
    'title' => 'Benachrichtigungen',
    'description' => 'Lege fest, worüber du informiert werden möchtest – getrennt für Slack und Push (auf dieses Gerät).',
    'saved' => 'Benachrichtigungseinstellungen gespeichert.',

    'channel_slack' => 'Slack',
    'channel_push' => 'Push',

    'push_device_title' => 'Push-Benachrichtigungen auf diesem Gerät',
    'push_device_description' => 'Push-Benachrichtigungen erreichen dich nur auf Geräten, auf denen du sie aktiviert hast. Schalte sie hier für dieses Gerät ein oder aus.',
    'push_device_toggle' => 'Auf diesem Gerät',

    'matrix_title' => 'Was und wie',
    'matrix_description' => 'Schalte einzelne Benachrichtigungen pro Kanal an oder aus. Standardmäßig ist alles an.',

    'slack_disabled_hint' => 'Verknüpfe dein Slack-Konto (über „Mit Slack anmelden“), um Slack-Benachrichtigungen zu erhalten.',
    'push_hint' => 'Push erreicht dich nur, wenn du oben auf diesem Gerät Push aktiviert hast.',

    'categories' => [
        'departures' => [
            'label' => 'Abholungen',
            'help' => 'Wenn dein Kind abgeholt wurde oder allein gegangen ist.',
        ],
        'excursions' => [
            'label' => 'Ausflüge',
            'help' => 'Neue Ausflüge und Erinnerungen zur Abstimmung.',
        ],
        'companion' => [
            'label' => 'Mitgehen',
            'help' => 'Wenn ein Kind mit deinem mitgehen möchte oder darauf geantwortet wird.',
        ],
        'missing_plan' => [
            'label' => 'Fehlender Wochenplan',
            'help' => 'Erinnerung, wenn für dein Kind noch kein Wochenplan hinterlegt ist.',
        ],
        'weekly_digest' => [
            'label' => 'Wochenüberblick',
            'help' => 'Montags: Essen und Aktivitäten der Woche plus eine kurze Übersicht für dein Kind.',
        ],
    ],
];
