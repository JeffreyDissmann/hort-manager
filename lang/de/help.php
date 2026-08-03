<?php

declare(strict_types=1);

return [
    'title' => 'Hilfe',
    'header' => 'Hilfe & Anleitung',
    'to_login' => 'Zur Anmeldung',
    'back_to_overview' => 'Alle Themen',
    'on_this_page' => 'In diesem Kapitel',

    // Intro (hub)
    'intro_title' => 'Willkommen beim Hort-Manager 👋',
    'intro_text' => 'Der Hort-Manager hilft Eltern und Erzieher:innen, gemeinsam den Überblick zu behalten – vor allem über das Wichtigste: <strong>wann und wie jedes Kind nach Hause geht</strong>. Du kannst alles bequem am Handy erledigen.',

    // Quick start (hub)
    'quick_start_title' => 'In 4 Schritten startklar',
    'steps' => [
        'Tippe auf „Mit Slack anmelden“ – dein Konto wird beim ersten Mal automatisch erstellt.',
        'Lege unter „Kinder“ dein Kind an.',
        'Trage den Stammplan ein: wann dein Kind an welchem Wochentag abgeholt wird.',
        'Fertig. Ab jetzt siehst du alles und bekommst wichtige Infos als Nachricht in Slack.',
    ],

    'topics_title' => 'Alle Themen',
    'topics_hint' => 'Such dir aus, worum es gerade geht – jedes Kapitel ist für sich lesbar.',

    'audiences' => [
        'all' => 'Für alle',
        'parents' => 'Für Eltern',
        'staff' => 'Nur Erzieher:innen',
    ],

    'topics' => [
        'getting-started' => [
            'title' => 'Anmelden & einrichten',
            'teaser' => 'Wie du reinkommst, die App aufs Handy legst und Sprache und Darstellung einstellst.',
            'audience' => 'all',
        ],
        'pickups' => [
            'title' => 'Abholung & Wochenplan',
            'teaser' => 'Stammplan, Änderungen für einzelne Tage und „geht mit einem anderen Kind mit“.',
            'audience' => 'all',
        ],
        'absences' => [
            'title' => 'Wenn ein Kind nicht da ist',
            'teaser' => 'Krank melden, „kommt nicht“ – und was „hortfrei“ bedeutet.',
            'audience' => 'all',
        ],
        'holidays' => [
            'title' => 'Ferien & Schließzeiten',
            'teaser' => 'Wann der Hort zu ist, und wie du dein Kind für die Ferienbetreuung anmeldest.',
            'audience' => 'all',
        ],
        'excursions' => [
            'title' => 'Ausflüge',
            'teaser' => 'Die Abstimmung: Kommt dein Kind mit? Ein Klick genügt.',
            'audience' => 'all',
        ],
        'slack' => [
            'title' => 'Slack & Benachrichtigungen',
            'teaser' => 'Was der Hort-Manager dir schickt, und wie du ihm einfach schreiben kannst.',
            'audience' => 'all',
        ],
        'staff' => [
            'title' => 'Für Erzieher:innen',
            'teaser' => 'Abhaken, Tagesprogramm, Ferien anlegen – und wer was darf.',
            'audience' => 'staff',
        ],
        'glossary' => [
            'title' => 'Kurz erklärt',
            'teaser' => 'Die Begriffe der App in einem Satz. Und wo du Hilfe bekommst.',
            'audience' => 'all',
        ],
    ],

    // ---------------------------------------------------------------- Anmelden
    'getting-started' => [
        'login_title' => 'Wie melde ich mich an?',
        'login_text_1' => 'Am einfachsten mit <strong>„Mit Slack anmelden“</strong> – ein eigenes Passwort brauchst du dafür nicht. Voraussetzung ist, dass du im Slack des Horts bist. Beim ersten Anmelden wird dein Konto automatisch angelegt.',
        'login_text_2' => 'Alternativ kannst du dich mit <strong>E-Mail und Passwort</strong> anmelden. Passwort vergessen? Über <strong>„Passwort vergessen?“</strong> auf der Anmeldeseite bekommst du einen Link per E-Mail, mit dem du dir ein neues Passwort setzt.',

        'children_title' => 'Dein Kind anlegen',
        'children_text' => 'Unter <strong>Kinder</strong> legst du dein Kind an und pflegst seinen Stammplan. Dort kannst du auch das zweite Elternteil verknüpfen, damit ihr beide alles seht und ändern könnt.',

        'install_title' => 'Als App installieren',
        'install_text' => 'Du kannst den Hort-Manager wie eine echte App auf dein Handy legen – dann startet er im Vollbild und kann dir Benachrichtigungen schicken.',
        'install_ios' => '<strong>iPhone (Safari):</strong> Teilen-Symbol antippen → „Zum Home-Bildschirm“.',
        'install_android' => '<strong>Android (Chrome):</strong> oben auf das Banner „Installieren“ tippen (oder Menü → „App installieren“).',
        'install_enable' => 'Danach im Menü oben rechts auf <strong>🔔 Benachrichtigungen an</strong> tippen und erlauben.',
        'install_note' => 'Hinweis: Auf dem iPhone funktionieren Benachrichtigungen nur, wenn die App vorher zum Home-Bildschirm hinzugefügt wurde.',

        'appearance_title' => 'Darstellung & Sprache',
        'appearance_theme' => '<strong>Hell oder dunkel:</strong> Unter <strong>Profil → Darstellung</strong> wählst du zwischen Hell, Dunkel und „Automatisch“ (folgt der Einstellung deines Geräts). Die Auswahl gilt pro Gerät.',
        'appearance_language' => '<strong>Sprache:</strong> Unter <strong>Profil → Sprache</strong> kannst du zwischen Deutsch und Englisch wechseln. Deutsch ist die Standardsprache.',
    ],

    // ---------------------------------------------------------------- Abholung
    'pickups' => [
        'intro' => 'Der Hort-Manager dreht sich um eine Frage: <strong>Wann und wie geht dein Kind heute nach Hause?</strong> Die Antwort kommt aus dem Stammplan – und lässt sich für einzelne Tage jederzeit ändern.',

        'stammplan_title' => 'Der Stammplan: die feste Woche',
        'stammplan_text' => 'Im Stammplan steht für jeden Wochentag, wann dein Kind geht und wie: <strong>abgeholt</strong> oder <strong>allein nach Hause</strong>. Bei „allein“ kannst du die Zeit genauer fassen – <strong>bis</strong>, <strong>genau um</strong> oder <strong>ab</strong> einer Uhrzeit. Ein Wochentag ohne Eintrag heißt <strong>hortfrei</strong>: an dem Tag ist dein Kind gar nicht im Hort.',
        'stammplan_where' => 'Zu finden unter <strong>Kinder → Kind auswählen</strong>. Alle Stammpläne zusammen siehst du unter <strong>Standard</strong>.',

        'week_title' => 'Der Wochenplan: die konkrete Woche',
        'week_text' => 'Der <strong>Wochenplan</strong> zeigt die ausgewählte Woche mit allem, was dazugehört: Essen, Aktivität, Hausaufgabenzeit und Ausflüge. Mit den Pfeilen (oder Wischen) blätterst du zwischen den Wochen.',
        'week_edit' => 'Tippe auf einen Tag, um genau diesen Tag zu ändern – Uhrzeit, Art des Nachhausegehens oder eine Krankmeldung. Geänderte Tage sind als <strong>„heute geändert“</strong> markiert, und mit <strong>„Auf Stammplan zurücksetzen“</strong> nimmst du die Änderung wieder zurück.',

        'today_title' => 'Heute: die Abholliste',
        'today_text' => 'Die Seite <strong>Heute</strong> ist die Übersicht für einen Tag: Wer wird wann abgeholt, wer geht allein, wer ist auf einem Ausflug. Erzieher:innen haken jedes Kind ab, sobald es geht – und du bekommst eine Nachricht. Hat ein Kind Geburtstag, steht dort ein 🎂.',

        'companion_title' => 'Mit einem anderen Kind nach Hause',
        'companion_intro' => 'Manchmal geht ein Kind mit einem anderen mit nach Hause – zum Spielen oder weil eine Familie beide mitnimmt. So funktioniert es:',
        'companion_points' => [
            'Wähl beim Ändern eines Tages bei „Art“ die Option <strong>„Geht mit einem anderen Kind mit“</strong> und dann das Kind. Die Abholzeit wird automatisch von diesem Kind übernommen – ändert sich dessen Zeit, ändert sich deine mit.',
            'Wird das andere Kind selbst <strong>abgeholt</strong>, ist alles sofort erledigt – ein Erwachsener ist ja dabei.',
            'Geht das andere Kind <strong>allein</strong>, muss dessen Familie erst zustimmen. Bis dahin steht bei allen anderen nur die normale Abholzeit – das „Mitgehen“ erscheint erst nach dem Ja.',
            'Oben auf „Heute“ und im Wochenplan siehst du eine Übersicht <strong>„Mit anderen nach Hause“</strong> – mit dem Stand der Zusage, und zum Bestätigen, wenn ein Kind mit deinem mitgehen möchte.',
        ],

        'late_title' => 'Kurzfristige Änderungen',
        'late_text' => 'Änderungen für <strong>heute</strong> sind jederzeit möglich. Liegen sie nach der vereinbarten Uhrzeit (meist 12:00), bekommt das Hort-Team eine kurze Nachricht, damit es die Änderung auch wirklich mitbekommt. Der Hinweis erscheint vor dem Speichern – erschrick nicht, es ist keine Fehlermeldung.',
    ],

    // ---------------------------------------------------------------- Nicht da
    'absences' => [
        'intro' => 'Es gibt <strong>drei verschiedene Arten</strong>, an denen dein Kind nicht im Hort ist. Sie sehen ähnlich aus, bedeuten aber Unterschiedliches:',

        'sick_title' => '1. Krank oder „kommt nicht“',
        'sick_text' => 'Dein Kind ist krank oder kommt an einem Tag aus einem anderen Grund nicht. Tippe auf <strong>„Krank“</strong> bzw. <strong>„Kommt nicht“</strong> – auf der Seite „Heute“ oder beim jeweiligen Tag im Wochenplan – und gib einen kurzen Grund an (z. B. „Erkältung“ oder „Familienbesuch“).',
        'sick_undo' => 'Das Kind steht dann an dem Tag nicht auf der Abholliste. Meldest du es doch wieder gesund, kommt es zurück auf seinen normalen Plan. Du kannst auch mehrere Tage auf einmal melden.',
        'sick_slack' => 'Noch schneller geht es in Slack: schreib dem Hort-Manager einfach „Mein Kind ist heute krank“ (siehe <strong>Slack & Benachrichtigungen</strong>).',

        'hortfrei_title' => '2. Hortfrei',
        'hortfrei_text' => '<strong>Hortfrei</strong> heißt: an diesem Wochentag ist dein Kind grundsätzlich nicht im Hort – so steht es im Stammplan. Das ist keine Abwesenheit und muss nirgends gemeldet werden.',
        'hortfrei_note' => 'Soll dein Kind an so einem Tag <em>ausnahmsweise doch</em> bleiben, tippe auf „Heute“ oder im Wochenplan auf seinen Namen bei „hortfrei“ und trage eine Abholzeit ein.',

        'closed_title' => '3. Schließzeit',
        'closed_text' => 'An einer <strong>Schließzeit</strong> hat der Hort selbst zu – Ferien, Brückentag oder Fortbildung. Dann gibt es für niemanden einen Hort-Tag: die Tage sind im Wochenplan grau, es gibt nichts einzutragen, und niemand muss sich abmelden.',
        'closed_link' => 'Alle Schließzeiten stehen im Menü unter <strong>Ferien &amp; Schließzeiten</strong> – und dort steht auch, wann es stattdessen eine Ferienbetreuung gibt.',
    ],

    // ---------------------------------------------------------------- Ferien
    'holidays' => [
        'intro' => 'In den Ferien gibt es zwei Möglichkeiten: der Hort ist <strong>geschlossen</strong>, oder es gibt eine <strong>Ferienbetreuung</strong>, für die du dein Kind pro Tag anmeldest. Die Schließzeiten stehen im Menü oben rechts, die Anmeldung unter <strong>Ausflüge &amp; Ferien</strong>.',

        'closed_title' => 'Schließzeiten',
        'closed_text' => 'Unter <strong>Ferien &amp; Schließzeiten</strong> stehen alle Zeiträume, an denen der Hort zu hat – mit Name und Datum, damit du deine eigenen Ferien danach planen kannst. Im Wochenplan sind diese Tage grau hinterlegt und gesperrt.',

        'care_title' => 'Ferienbetreuung: Tag für Tag anmelden',
        'care_text' => 'Bei einer <strong>Ferienbetreuung</strong> ist der Hort geöffnet – aber nur für die Kinder, die angemeldet sind. Weil in den Ferien keine Schule ist, gilt der normale Stammplan nicht: du sagst für <strong>jeden einzelnen Tag</strong>, ob dein Kind kommt.',
        'care_points' => [
            'Öffne unten <strong>Ausflüge &amp; Ferien</strong>. Dort steht jeder angebotene Tag mit seiner <strong>Betreuungszeit</strong> (z. B. 08:30–16:00) – gleich neben den Ausflügen, die eine Antwort brauchen.',
            'Setz bei deinem Kind einen Haken bei den Tagen, an denen es kommt – und speichere. <strong>Keinen Tag</strong> auszuwählen ist eine genauso gültige Antwort wie alle.',
            'Achte auf den <strong>Anmeldeschluss</strong>. Danach kannst du selbst nichts mehr ändern; sprich das Hort-Team an, es kann dich noch eintragen.',
            'Du bekommst eine Nachricht, sobald eine Ferienbetreuung geöffnet ist, und eine Erinnerung am Tag des Anmeldeschlusses, falls du noch nicht geantwortet hast.',
        ],

        'care_day_title' => 'So ein Ferientag läuft wie jeder andere',
        'care_day_text' => 'Ist dein Kind angemeldet, ist der Tag ein ganz normaler Hort-Tag: es steht auf der Abholliste, wird abgeholt oder geht allein, kann mit einem anderen Kind mitgehen, und du bekommst wie sonst eine Nachricht, wenn es gegangen ist.',
        'care_day_points' => [
            'Als Abholzeit ist zunächst das <strong>Ende der Betreuungszeit</strong> eingetragen. Du kannst sie wie an jedem anderen Tag im Wochenplan ändern.',
            'Ist dein Kind angemeldet und wird dann krank, meldest du es ganz normal krank – <strong>der Platz bleibt</strong> erhalten.',
            'Hausaufgaben gibt es in den Ferien nicht; Essen und Aktivität stehen wie gewohnt beim Tag.',
            'Fällt eine Schließzeit auf denselben Tag, gilt die Schließzeit – dann ist der Hort zu.',
        ],
    ],

    // ---------------------------------------------------------------- Ausflüge
    'excursions' => [
        'intro' => 'Steht ein Ausflug an, wirst du gefragt, ob dein Kind mitkommt. Geplant werden Ausflüge vom Hort-Team.',
        'points' => [
            'Du bekommst eine Nachricht mit allen Infos – Ziel, Datum, Abfahrt und Rückkehr – und zwei Knöpfen: <strong>Ja</strong> oder <strong>Nein</strong>.',
            'Antworten kannst du <strong>direkt in Slack</strong> oder in der App unter <strong>Ausflüge</strong>. Beides ist dasselbe; ihr beide Elternteile seht die Antwort.',
            'Bis zum <strong>Antwortschluss</strong> kannst du deine Antwort jederzeit ändern. Hast du noch nicht geantwortet, erinnert dich der Hort-Manager.',
            'Am Ausflugstag steht dein Kind mit einem 🚌 auf der Abholliste – abgeholt wird es danach ganz normal.',
        ],
    ],

    // ---------------------------------------------------------------- Slack
    'slack' => [
        'slack_title' => 'Was passiert in Slack?',
        'slack_intro' => 'Der Hort-Manager ist mit dem Slack des Horts verbunden, damit du nichts verpasst:',
        'slack_points' => [
            'Du bekommst eine kurze Nachricht, sobald dein Kind abgeholt wurde oder allein gegangen ist.',
            'Bei einem neuen Ausflug oder einer neuen Ferienbetreuung schickt dir der Hort-Manager alle Infos – du kannst direkt in Slack antworten.',
            'Du kannst dem Hort-Manager auch einfach schreiben (siehe unten) oder „/hort“ tippen, um in die App zu springen.',
            'Über die App „Hort-Manager“ in deiner Slack-Seitenleiste kommst du jederzeit hierher.',
        ],

        'assistant_title' => 'Einfach hinschreiben',
        'assistant_text' => '<strong>Schreib dem Hort-Manager direkt in Slack.</strong> Er versteht ganz normale Sätze – zum Beispiel:',
        'assistant_examples' => [
            'Mein Kind ist heute krank.',
            'Lena wird morgen erst um 16:30 abgeholt.',
            'Tom geht ab Montag allein nach Hause.',
            'Kommt Lena beim Zoo-Ausflug mit? Ja.',
            'Wann geht Tom heute?',
        ],
        'assistant_note' => 'Das geht per Direktnachricht an den „Hort-Manager“ in Slack oder mit „/hort …“. Er kümmert sich nur um deine eigenen Kinder und bestätigt dir kurz, was er eingetragen hat. Prüf die Antwort – bei einem Missverständnis schreib einfach die richtige Angabe nach.',

        'notifications_title' => 'Welche Benachrichtigungen bekomme ich?',
        'notifications_intro' => 'Der Hort-Manager meldet sich bei dir, wenn etwas Wichtiges passiert – als <strong>Push-Nachricht</strong> auf deinem Gerät und, wenn dein Konto mit Slack verbunden ist, zusätzlich als <strong>Slack-Nachricht</strong>. Beide zeigen dasselbe; ein Kanal genügt.',
        'notifications_points' => [
            '<strong>Kind abgeholt / allein gegangen:</strong> Sobald das Hort-Team dein Kind abhakt, bekommst du Bescheid.',
            '<strong>Neuer Ausflug:</strong> Du wirst zur Abstimmung eingeladen – mit einer Erinnerung, falls du noch nicht geantwortet hast.',
            '<strong>Ferienbetreuung:</strong> Du erfährst, wenn die Anmeldung offen ist, und wirst am Anmeldeschluss erinnert.',
            '<strong>Ein Kind möchte mit deinem mitgehen:</strong> Geht dein Kind allein und ein anderes soll mitkommen, fragt dich dessen Familie um Erlaubnis – direkt mit „Ja/Nein“ in Slack oder in der App.',
            '<strong>Antwort aufs Mitgehen:</strong> Hast du gefragt, ob dein Kind mit einem anderen mitgehen darf, erfährst du, sobald die andere Familie zu- oder abgesagt hat.',
            '<strong>Wochenüberblick:</strong> Montags bekommst du die kommende Woche auf einen Blick – Programm, Ausflüge und der Plan deiner Kinder.',
        ],
        'notifications_settings' => 'Was du bekommen möchtest, stellst du unter <strong>Profil → Benachrichtigungen</strong> ein – getrennt für Slack und Push. Antworten kannst du überall; beide Seiten bleiben automatisch auf demselben Stand.',
    ],

    // ---------------------------------------------------------------- Erzieher
    'staff' => [
        'intro' => 'Dieses Kapitel richtet sich an das Hort-Team. Eltern dürfen gern mitlesen – es erklärt, was hinter den Kulissen passiert.',

        'board_title' => 'Abhaken auf „Heute“',
        'board_text' => 'Auf <strong>Heute</strong> steht die Abholliste des Tages, sortiert nach Uhrzeit. Tippe ein Kind an, sobald es geht – seine Familie bekommt sofort eine Nachricht. Die Abholzeit eines Kindes kannst du hier auch kurzfristig ändern.',

        'program_title' => 'Tagesprogramm',
        'program_text' => 'Unter <strong>Programm</strong> trägst du für die Woche Mittagessen, Aktivität und die Hausaufgabenzeiten ein – „keine Hausaufgaben“ ist ebenfalls möglich. Eltern sehen das im Wochenplan und auf „Heute“.',
        'program_reminder' => 'Fehlt am Montag noch ein Mittagessen für die Woche, erinnert dich der Hort-Manager kurz vor dem Wochenüberblick der Eltern. Auf derselben Seite stellst du auch die Uhrzeit für <strong>späte Änderungen</strong> und den Versand des Wochenüberblicks ein.',

        'holidays_title' => 'Ferien & Schließzeiten anlegen',
        'holidays_points' => [
            'Unter <strong>Ferien &amp; Schließzeiten</strong> legst du beides an: einen geschlossenen Zeitraum oder eine <strong>Ferienbetreuung</strong>. Ein einzelner Tag ist einfach ein Zeitraum von einem Tag.',
            'Ein Tipp auf <strong>Öffnen</strong> bringt dich zum Zeitraum selbst – dort stehen die angebotenen Tage und die Anmeldungen, so wie ein Ausflug seine Antworten bei sich hat.',
            'Bei einer Ferienbetreuung wird jeder Werktag des Zeitraums mit der Standard-Betreuungszeit angeboten (einstellbar unter „Programm“). Du kannst die Zeiten pro Tag anpassen oder einen Tag ganz herausnehmen – und ihn später wieder anbieten.',
            'Der <strong>Anmeldeschluss</strong> gehört zum Zeitraum. Bis dahin melden Eltern selbst an; danach trägst du auf der Seite des Zeitraums ein – für jedes Kind.',
            'Eine Schließzeit sticht eine Ferienbetreuung: liegt beides auf demselben Tag, ist der Hort zu. Ein Ausflug im Zeitraum muss vorher verschoben oder abgesagt werden.',
        ],

        'roles_title' => 'Wer darf was?',
        'role_parents' => '<strong>Eltern</strong> sehen alles, pflegen ihre eigenen Kinder und antworten auf Ausflüge und Ferienbetreuung.',
        'role_staff' => '<strong>Erzieher:innen</strong> haken Abholungen ab, planen Ausflüge, Programm und Ferien – und dürfen für jedes Kind eintragen.',
        'role_admins' => '<strong>Admins</strong> verwalten zusätzlich die Benutzer und vergeben die Rollen.',
    ],

    // ---------------------------------------------------------------- Glossar
    'glossary' => [
        'intro' => 'Die Begriffe der App – jeweils in einem Satz.',
        'terms' => [
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
            'hortfrei' => [
                'term' => 'Hortfrei',
                'def' => 'Ein Wochentag, an dem ein Kind laut Stammplan gar nicht im Hort ist. Keine Abwesenheit, nichts zu melden.',
            ],
            'closure' => [
                'term' => 'Schließzeit',
                'def' => 'Ein Zeitraum, in dem der Hort für alle geschlossen ist – Ferien, Brückentag, Fortbildung.',
            ],
            'care' => [
                'term' => 'Ferienbetreuung',
                'def' => 'Betreuung in den Ferien, für die Eltern ihr Kind Tag für Tag anmelden. Nur angemeldete Kinder sind da.',
            ],
        ],

        'questions_title' => 'Noch Fragen?',
        'questions_text' => 'Bei Fragen oder Problemen mit der App wende dich an den Entwickler <strong>Jeffrey Dissmann</strong>:',
    ],
];
