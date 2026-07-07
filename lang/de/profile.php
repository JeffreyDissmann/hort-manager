<?php

declare(strict_types=1);

return [
    'title' => 'Profil',

    // Language switcher
    'language' => 'Sprache',
    'language_help' => 'Wähle die Sprache der Benutzeroberfläche.',
    'language_saved' => 'Sprache gespeichert.',

    // Theme (dark mode) — stored per device
    'theme' => 'Darstellung',
    'theme_help' => 'Helles oder dunkles Design – oder automatisch nach deinem Gerät. Die Einstellung gilt pro Gerät.',
    'theme_system' => 'Automatisch',
    'theme_light' => 'Hell',
    'theme_dark' => 'Dunkel',

    // Profile information
    'information_title' => 'Profil-Informationen',
    'information_description' => 'Aktualisiere die Profil-Informationen und die E-Mail-Adresse deines Kontos.',
    'name' => 'Name',
    'email' => 'E-Mail-Adresse',
    'email_unverified' => 'Deine E-Mail-Adresse ist nicht bestätigt.',
    'email_resend' => 'Klicke hier, um die Bestätigungs-E-Mail erneut zu senden.',
    'email_verification_sent' => 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',

    // Password
    'password_change' => 'Passwort ändern',
    'password_set' => 'Passwort festlegen',
    'password_help' => 'Verwende ein langes, zufälliges Passwort, um dein Konto zu schützen.',
    'password_help_slack' => 'Du bist mit Slack angemeldet. Lege ein Passwort fest, um dich auch mit deiner E-Mail-Adresse anmelden zu können.',
    'current_password' => 'Aktuelles Passwort',
    'new_password' => 'Neues Passwort',
    'confirm_password' => 'Passwort bestätigen',

    // Notifications
    'notifications_title' => 'Benachrichtigungen',
    'notifications_description' => 'Erhalte eine Push-Nachricht auf dieses Gerät, wenn dein Kind abgeholt wurde oder allein gegangen ist – und bei neuen Ausflügen samt Erinnerung zur Abstimmung.',
    'push' => 'Push-Benachrichtigungen',
    'on' => 'An',
    'off' => 'Aus',
    'notifications_unsupported' => 'Dieser Browser kann keine Benachrichtigungen anzeigen. Auf dem iPhone musst du die App dafür zuerst über <strong>Teilen → Zum Home-Bildschirm</strong> installieren und sie von dort öffnen.',
    'notifications_per_device' => 'Die Einstellung gilt pro Gerät – aktiviere sie auf jedem Handy oder Rechner, auf dem du benachrichtigt werden möchtest.',

    // Delete account
    'delete_account' => 'Konto löschen',
    'delete_description' => 'Wenn dein Konto gelöscht wird, werden alle zugehörigen Daten dauerhaft entfernt. Bitte sichere vorher alle Daten, die du behalten möchtest.',
    'delete_confirm_title' => 'Möchtest du dein Konto wirklich löschen?',
    'delete_confirm_description' => 'Wenn dein Konto gelöscht wird, werden alle zugehörigen Daten dauerhaft entfernt. Bitte gib dein Passwort ein, um die Löschung zu bestätigen.',
    'password' => 'Passwort',

    // Push notification errors (from usePush)
    'push_blocked' => 'Benachrichtigungen sind für diese Seite blockiert. Bitte erlaube sie in den Browser-Einstellungen.',
    'push_not_allowed' => 'Benachrichtigungen wurden nicht erlaubt.',
    'push_failed' => 'Benachrichtigungen konnten nicht aktiviert werden. Bitte versuche es später erneut.',
];
