<?php

declare(strict_types=1);

return [
    'title' => 'Hilfe',
    'header' => 'Hilfe & Anleitung',
    'to_login' => 'Zur Anmeldung',

    // Intro
    'intro_title' => 'Willkommen beim Hort-Manager 👋',
    'intro_text' => 'Der Hort-Manager hilft Eltern und Erzieher:innen, gemeinsam den Überblick zu behalten – vor allem über das Wichtigste: <strong>wann und wie jedes Kind nach Hause geht</strong>. Du kannst alles bequem am Handy erledigen.',

    // Quick start
    'quick_start_title' => 'In 4 Schritten startklar',
    'steps' => [
        'Tippe auf „Mit Slack anmelden“ – dein Konto wird beim ersten Mal automatisch erstellt.',
        'Lege unter „Kinder“ dein Kind an.',
        'Trage den Stammplan ein: wann dein Kind an welchem Wochentag abgeholt wird.',
        'Fertig. Ab jetzt siehst du alles und bekommst wichtige Infos als Nachricht in Slack.',
    ],

    // Login
    'login_title' => 'Wie melde ich mich an?',
    'login_text_1' => 'Am einfachsten mit <strong>„Mit Slack anmelden“</strong> – ein eigenes Passwort brauchst du dafür nicht. Voraussetzung ist, dass du im Slack des Horts bist. Beim ersten Anmelden wird dein Konto automatisch angelegt.',
    'login_text_2' => 'Alternativ kannst du dich mit <strong>E-Mail und Passwort</strong> anmelden. Passwort vergessen? Über <strong>„Passwort vergessen?“</strong> auf der Anmeldeseite bekommst du einen Link per E-Mail, mit dem du dir ein neues Passwort setzt.',

    // Areas
    'areas_title' => 'Was kann ich wo machen?',
    'areas' => [
        'today' => [
            'title' => 'Heute',
            'audience' => 'Für alle',
            'text' => 'Die Übersicht für einen Tag: Wer wird wann abgeholt, wer geht allein nach Hause, wer ist auf einem Ausflug. Erzieher:innen haken jedes Kind ab, sobald es geht. Hat ein Kind Geburtstag, siehst du das hier mit einem 🎂. Mit den Pfeilen oder per Tipp auf das Datum kannst du auch jeden anderen Tag ansehen – z. B. was am Freitag mit Essen und Aktivität geplant ist.',
        ],
        'pickup_plan' => [
            'title' => 'Wochenplan',
            'audience' => 'Für alle',
            'text' => 'Die ganze Woche auf einen Blick – mit Essen, Aktivität, Hausaufgaben und Ausflügen. Tippe auf einen Tag, um ihn anzupassen: Uhrzeit, ob dein Kind abgeholt wird, allein geht (auch „bis / genau um / ab“ einer Uhrzeit) oder mit einem anderen Kind mitgeht – oder melde es krank bzw. „kommt nicht“.',
        ],
        'excursions' => [
            'title' => 'Ausflüge',
            'audience' => 'Eltern antworten',
            'text' => 'Steht ein Ausflug an, wirst du gefragt, ob dein Kind mitkommt. Ein Klick auf Ja oder Nein genügt – in der App oder direkt in Slack. Erzieher:innen planen die Ausflüge.',
        ],
        'children' => [
            'title' => 'Kinder',
            'audience' => 'Eltern & Erzieher:innen',
            'text' => 'Hier legst du dein Kind an und pflegst seinen Stammplan. Du kannst auch das zweite Elternteil verknüpfen, damit ihr beide alles seht und ändern könnt.',
        ],
        'program' => [
            'title' => 'Programm',
            'audience' => 'Nur Erzieher:innen',
            'text' => 'Erzieher:innen tragen Mittagessen, Aktivität und die Hausaufgaben-Zeiten der Woche ein – auch „keine Hausaufgaben“ ist möglich. Eltern sehen das mit; die Hausaufgabenzeit erscheint auch auf „Heute“.',
        ],
    ],

    // Slack
    'slack_title' => 'Was passiert in Slack?',
    'slack_intro' => 'Der Hort-Manager ist mit dem Slack des Horts verbunden, damit du nichts verpasst:',
    'slack_points' => [
        'Du bekommst eine kurze Nachricht, sobald dein Kind abgeholt wurde oder allein gegangen ist.',
        'Bei einem neuen Ausflug schickt dir der Hort-Manager alle Infos mit Ja/Nein-Knöpfen – du kannst direkt in Slack antworten.',
        'Du kannst dem Hort-Manager auch einfach schreiben (siehe unten) oder „/hort“ tippen, um in die App zu springen.',
        'Über die App „Hort-Manager“ in deiner Slack-Seitenleiste kommst du jederzeit hierher.',
    ],

    // Assistant & sick reports
    'assistant_title' => 'Krank melden & schnelle Änderungen',
    'assistant_text_1' => 'Ist dein Kind krank oder kommt an einem Tag nicht? Tippe auf <strong>„Krank“</strong> bzw. <strong>„Kommt nicht“</strong> – auf der Seite „Heute“ oder beim jeweiligen Tag im Wochenplan – und gib einen kurzen Grund an (z. B. „Erkältung“ oder „Familienbesuch“). Genauso trägst du dort kurzfristig eine andere Abholzeit ein.',
    'assistant_text_2' => 'Noch einfacher: <strong>Schreib es dem Hort-Manager direkt in Slack.</strong> Er versteht ganz normale Sätze – zum Beispiel:',
    'assistant_examples' => [
        'Mein Kind ist heute krank.',
        'Lena wird morgen erst um 16:30 abgeholt.',
        'Tom geht ab Montag allein nach Hause.',
        'Kommt Lena beim Zoo-Ausflug mit? Ja.',
        'Wann geht Tom heute?',
    ],
    'assistant_note' => 'Das geht per Direktnachricht an den „Hort-Manager“ in Slack oder mit „/hort …“. Er kümmert sich nur um deine eigenen Kinder und bestätigt dir kurz, was er eingetragen hat. Prüf die Antwort – bei einem Missverständnis schreib einfach die richtige Angabe nach.',

    // Mit einem anderen Kind mitgehen
    'companion_title' => 'Mit einem anderen Kind nach Hause',
    'companion_intro' => 'Manchmal geht ein Kind mit einem anderen mit nach Hause – zum Spielen oder weil eine Familie beide mitnimmt. So funktioniert es:',
    'companion_points' => [
        'Wähl im Wochenplan bei „Art“ die Option <strong>„Geht mit einem anderen Kind mit“</strong> und dann das Kind. Die Abholzeit wird automatisch von diesem Kind übernommen – ändert sich dessen Zeit, ändert sich deine mit.',
        'Wird das andere Kind selbst <strong>abgeholt</strong>, ist alles sofort erledigt – ein Erwachsener ist ja dabei.',
        'Geht das andere Kind <strong>allein</strong>, muss dessen Familie erst zustimmen. Bis dahin steht bei allen anderen nur die normale Abholzeit – das „Mitgehen“ erscheint erst nach dem Ja.',
        'Oben auf „Heute“ und im Wochenplan siehst du eine Übersicht „Mit anderen nach Hause“ – mit dem Stand der Zusage, und zum Bestätigen, wenn ein Kind mit deinem mitgehen möchte.',
    ],

    // Notifications
    'notifications_title' => 'Welche Benachrichtigungen bekomme ich?',
    'notifications_intro' => 'Der Hort-Manager meldet sich bei dir, wenn etwas Wichtiges passiert – als <strong>Push-Nachricht</strong> auf deinem Gerät und, wenn dein Konto mit Slack verbunden ist, zusätzlich als <strong>Slack-Nachricht</strong>. Beide zeigen dasselbe; ein Kanal genügt.',
    'notifications_points' => [
        '<strong>Kind abgeholt / allein gegangen:</strong> Sobald das Hort-Team dein Kind abhakt, bekommst du Bescheid.',
        '<strong>Neuer Ausflug:</strong> Du wirst zur Abstimmung eingeladen – mit einer Erinnerung, falls du noch nicht geantwortet hast.',
        '<strong>Ein Kind möchte mit deinem mitgehen:</strong> Geht dein Kind allein und ein anderes soll mitkommen, fragt dich dessen Familie um Erlaubnis – direkt mit „Ja/Nein“ in Slack oder in der App.',
        '<strong>Antwort aufs Mitgehen:</strong> Hast du gefragt, ob dein Kind mit einem anderen mitgehen darf, erfährst du, sobald die andere Familie zu- oder abgesagt hat.',
        '<strong>Mitgehen nicht möglich:</strong> Wird das andere Kind krank oder abwesend gemeldet, sagen wir dir Bescheid, damit du die Abholung neu planen kannst.',
    ],
    'notifications_note' => 'Antworten kannst du überall – in Slack oder in der App; beide Seiten bleiben automatisch auf demselben Stand.',

    // Install as app
    'install_title' => 'Als App installieren',
    'install_text' => 'Du kannst den Hort-Manager wie eine echte App auf dein Handy legen – dann startet er im Vollbild und kann dir Benachrichtigungen schicken.',
    'install_ios' => '<strong>iPhone (Safari):</strong> Teilen-Symbol antippen → „Zum Home-Bildschirm“.',
    'install_android' => '<strong>Android (Chrome):</strong> oben auf das Banner „Installieren“ tippen (oder Menü → „App installieren“).',
    'install_enable' => 'Danach im Menü oben rechts auf <strong>🔔 Benachrichtigungen an</strong> tippen und erlauben.',
    'install_note' => 'Hinweis: Auf dem iPhone funktionieren Benachrichtigungen nur, wenn die App vorher zum Home-Bildschirm hinzugefügt wurde.',

    // Appearance & language
    'appearance_title' => 'Darstellung & Sprache',
    'appearance_theme' => '<strong>Hell oder dunkel:</strong> Unter <strong>Profil → Darstellung</strong> wählst du zwischen Hell, Dunkel und „Automatisch“ (folgt der Einstellung deines Geräts). Die Auswahl gilt pro Gerät.',
    'appearance_language' => '<strong>Sprache:</strong> Unter <strong>Profil → Sprache</strong> kannst du zwischen Deutsch und Englisch wechseln. Deutsch ist die Standardsprache.',

    // Roles
    'roles_title' => 'Wer darf was?',
    'role_parents' => '<strong>Eltern</strong> sehen alles, pflegen ihre eigenen Kinder und antworten auf Ausflüge.',
    'role_staff' => '<strong>Erzieher:innen</strong> haken Abholungen ab und planen Ausflüge und das Programm.',
    'role_admins' => '<strong>Admins</strong> verwalten zusätzlich die Benutzer und vergeben die Rollen.',

    // Glossary
    'glossary_title' => 'Kurz erklärt',
    'glossary' => [
        'stammplan' => [
            'term' => 'Stammplan',
            'def' => 'Die festen, wöchentlich gleichen Abholzeiten eines Kindes – die Grundlage für den Wochenplan.',
        ],
        'pickup_plan' => [
            'term' => 'Wochenplan',
            'def' => 'Der konkrete Plan für eine bestimmte Woche. Er kommt aus dem Stammplan, kann aber pro Tag angepasst werden.',
        ],
        'departure' => [
            'term' => 'abgeholt / allein / mit einem Kind',
            'def' => 'Die drei Arten, wie ein Kind nach Hause kommt: abgeholt, allein nach Hause, oder gemeinsam mit einem anderen Kind.',
        ],
        'companion' => [
            'term' => 'Mit einem anderen Kind mitgehen',
            'def' => 'Ein Kind geht mit einem anderen mit nach Hause und übernimmt dessen Abholzeit. Geht das andere Kind allein, muss dessen Familie zustimmen.',
        ],
        'absence' => [
            'term' => 'Krank / Kommt nicht',
            'def' => 'Ein Kind ist für einen Tag als krank oder abwesend gemeldet – dann ist es an dem Tag nicht auf der Abholliste.',
        ],
    ],

    // Questions
    'questions_title' => 'Noch Fragen?',
    'questions_text' => 'Bei Fragen oder Problemen mit der App wende dich an den Entwickler <strong>Jeffrey Dissmann</strong>:',
];
