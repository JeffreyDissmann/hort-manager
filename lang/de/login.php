<?php

declare(strict_types=1);

return [
    // Login
    'title' => 'Anmelden',
    'sign_in_with_slack' => 'Mit Slack anmelden',
    'or_with_email' => 'oder mit E-Mail',
    'email' => 'E-Mail-Adresse',
    'password' => 'Passwort',
    'remember_me' => 'Angemeldet bleiben',
    'forgot_password' => 'Passwort vergessen?',
    'sign_in' => 'Anmelden',
    'new_here' => 'Neu hier?',
    'how_it_works' => 'So funktioniert der Hort-Manager',

    // Erste Anmeldung — meist neue Eltern nach den Ferien. Es gibt keine
    // Selbstregistrierung: entweder Slack legt das Konto an, oder es gibt schon eins.
    'first_time_here' => 'Zum ersten Mal hier?',
    'first_slack_title' => '1. Mit Slack anmelden',
    'first_slack_text' => 'Der einfachste Weg: oben auf <strong>„Mit Slack anmelden“</strong> tippen. Du brauchst kein eigenes Passwort – dein Konto wird beim ersten Mal automatisch angelegt. Voraussetzung ist, dass du im Slack des Horts bist.',
    // Der eigentliche Knackpunkt: wer nicht im Slack ist, probiert es sonst immer
    // wieder mit dem Slack-Knopf, statt den zweiten Weg zu nehmen.
    'first_slack_fallback' => '<strong>Klappt das nicht?</strong> Wenn du (noch) nicht im Slack des Horts bist oder die Slack-Anmeldung eine Fehlermeldung zeigt, dann nimm einfach Weg 2 – das funktioniert genauso gut.',
    'first_password_title' => '2. Ohne Slack: erstes Passwort setzen',
    // :url – der Weg dorthin ist der Punkt dieses Absatzes, also ist er anklickbar.
    'first_password_text' => 'Gehe auf <a href=":url"><strong>„Passwort vergessen?“</strong></a> und gib deine E-Mail-Adresse an – die, die der Hort von dir hat. Über den Link aus der E-Mail vergibst du dein erstes Passwort und meldest dich danach ganz normal mit E-Mail und Passwort an.',
    'first_stuck' => 'Kommt keine E-Mail an? Dann gibt es noch kein Konto für diese Adresse – melde dich kurz beim Hort-Team.',

    // Forgot password
    'forgot_title' => 'Passwort vergessen',
    'forgot_intro' => 'Passwort vergessen? Kein Problem. Gib einfach deine E-Mail-Adresse an und wir senden dir einen Link, mit dem du ein neues Passwort wählen kannst.',
    'send_reset_link' => 'Link zum Zurücksetzen senden',

    // Reset password
    'reset_title' => 'Passwort zurücksetzen',
    'confirm_password' => 'Passwort bestätigen',
    'reset_password' => 'Passwort zurücksetzen',

    // Confirm password
    'confirm_title' => 'Passwort bestätigen',
    'confirm_intro' => 'Dies ist ein geschützter Bereich. Bitte bestätige dein Passwort, um fortzufahren.',

    // Verify email
    'verify_title' => 'E-Mail-Bestätigung',
    'verify_intro' => 'Danke für deine Registrierung! Bitte bestätige deine E-Mail-Adresse über den Link, den wir dir gerade geschickt haben. Falls du keine E-Mail erhalten hast, senden wir dir gerne eine neue.',
    'verify_link_sent' => 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',
    'resend_verification' => 'Bestätigungs-E-Mail erneut senden',
    'log_out' => 'Abmelden',
];
