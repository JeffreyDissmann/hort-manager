<?php

declare(strict_types=1);

return [
    'title' => 'Profile',

    // Language switcher
    'language' => 'Language',
    'language_help' => 'Choose the language of the user interface.',
    'language_saved' => 'Language saved.',

    // Theme (dark mode) — stored per device
    'theme' => 'Appearance',
    'theme_help' => 'Light or dark design — or automatically match your device. This setting applies per device.',
    'theme_system' => 'Automatic',
    'theme_light' => 'Light',
    'theme_dark' => 'Dark',

    // Profile information
    'information_title' => 'Profile Information',
    'information_description' => "Update your account's profile information and email address.",
    'name' => 'Name',
    'email' => 'Email address',
    'email_unverified' => 'Your email address is unverified.',
    'email_resend' => 'Click here to resend the verification email.',
    'email_verification_sent' => 'A new verification link has been sent to your email address.',

    // Password
    'password_change' => 'Change password',
    'password_set' => 'Set password',
    'password_help' => 'Use a long, random password to keep your account secure.',
    'password_help_slack' => 'You are signed in with Slack. Set a password so you can also sign in with your email address.',
    'current_password' => 'Current password',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm password',

    // Notifications
    'notifications_title' => 'Notifications',
    'notifications_description' => 'Get a push notification on this device when your child has been picked up or has left on their own – and for new excursions along with a reminder to respond.',
    'push' => 'Push notifications',
    'on' => 'On',
    'off' => 'Off',
    'notifications_unsupported' => 'This browser cannot show notifications. On the iPhone you first have to install the app via <strong>Share → Add to Home Screen</strong> and open it from there.',
    'notifications_per_device' => 'The setting applies per device – enable it on every phone or computer where you want to be notified.',

    // Delete account
    'delete_account' => 'Delete account',
    'delete_description' => 'Once your account is deleted, all of its resources and data will be permanently removed. Please back up any data you want to keep beforehand.',
    'delete_confirm_title' => 'Are you sure you want to delete your account?',
    'delete_confirm_description' => 'Once your account is deleted, all of its resources and data will be permanently removed. Please enter your password to confirm the deletion.',
    'password' => 'Password',

    // Push notification errors (from usePush)
    'push_blocked' => 'Notifications are blocked for this site. Please allow them in your browser settings.',
    'push_not_allowed' => 'Notifications were not allowed.',
    'push_failed' => 'Could not enable notifications. Please try again later.',
];
