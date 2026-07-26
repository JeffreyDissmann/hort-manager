<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        // "Sign in with Slack" SSO (Socialite).
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'redirect' => env('SLACK_REDIRECT_URI'),
        // Verifies interactive button callbacks (Slack signs each request with it).
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        // Pre-selects the Hort's workspace so users skip the "which workspace" step.
        // `team` is the team id (T…); `workspace` is the subdomain in <workspace>.slack.com
        // and is what actually skips Slack's workspace picker.
        'team' => env('SLACK_TEAM_ID'),
        'workspace' => env('SLACK_WORKSPACE'),

        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Paperless-ngx document archive. With no url+token the integration is inert
    // (booking↔document linking and receipt auto-match silently disabled).
    'paperless' => [
        'url' => env('PAPERLESS_URL'),
        'token' => env('PAPERLESS_TOKEN'),
        // Id of a pre-existing Paperless custom field. When set, linking a booking
        // writes the booking's deep-link URL into this field on the document, so the
        // link is visible/filterable from inside Paperless too. The app never creates
        // or alters field definitions. Unset → one-directional link only.
        'booking_field' => env('PAPERLESS_BOOKING_FIELD'),
        // Id of a monetary custom field holding the document's total. When set, receipt
        // suggestions/matching filter on an exact amount match (a near-unique signal).
        'amount_field' => env('PAPERLESS_AMOUNT_FIELD'),
        // Id of a „select" custom field holding the payment type (Bar, EC-Karte, …). When
        // set, the receipt-assignment wizard shows it and can filter by it.
        'payment_field' => env('PAPERLESS_PAYMENT_FIELD'),
    ],

];
