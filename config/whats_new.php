<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Was ist neu? — user-facing release notes
|--------------------------------------------------------------------------
|
| Shown as a popup the first time someone opens the app after an update, and
| reopenable via "Was ist neu?" in the menu. Newest entry first. Write these
| for parents/staff in plain German — this is separate from the technical
| CHANGELOG.md. Bump 'version' whenever you want the popup to show again.
|
*/

return [
    [
        'version' => '2026.07.07',
        'date' => '2026-07-07',
        'title' => 'Dunkelmodus 🌙',
        'items' => [
            '🌙 Der Hort-Manager gibt es jetzt auch in Dunkel – angenehmer für die Augen am Abend.',
            '⚙️ Stell es unter „Profil → Darstellung" ein: Hell, Dunkel oder „Automatisch" (folgt der Einstellung deines Geräts).',
            'ℹ️ Die Auswahl gilt pro Gerät – so kann dein Handy dunkel und dein Rechner hell sein.',
        ],
    ],
    [
        'version' => '2026.07.06',
        'date' => '2026-07-06',
        'title' => 'Übersichtlicherer Wochenplan – und der Stammplan als eigene Seite',
        'items' => [
            '📅 Der „Wochenplan" zeigt jetzt viel deutlicher, welche Woche du gerade ansiehst: „Aktuelle Woche", „Nächste Woche" oder „in 2 Wochen" – und der heutige Tag ist farbig hervorgehoben.',
            '🗂️ Der feste Standard-Wochenplan („Stammplan") hat jetzt eine eigene Seite im Menü. So sind die regulären Zeiten und der aktuelle Wochenplan sauber getrennt.',
            '🙌 Idee dazu von Yvonne – vielen Dank!',
        ],
    ],
    [
        'version' => '2026.07.04',
        'date' => '2026-07-04',
        'title' => 'Wer kommt beim Ausflug mit? · Und: Englisch als Sprache',
        'items' => [
            '🚌 Beim Ausflug siehst du jetzt mit „Alle Kinder anzeigen", wer noch mitkommt – auf der Ausflüge-Seite und am Ausflugstag auch auf „Heute".',
            '🌍 Du kannst die App-Sprache umstellen: unter „Profil → Sprache" zwischen Deutsch und Englisch wählen. Deutsch bleibt die Standardsprache.',
            '🌍 You can switch the app language under „Profil → Language" (German/English). German stays the default.',
        ],
    ],
    [
        'version' => '2026.07.03',
        'date' => '2026-07-03',
        'title' => 'Schreib dem Hort-Manager einfach – direkt in Slack',
        'items' => [
            '💬 Du kannst dem Hort-Manager in Slack jetzt ganz normal schreiben: „Tom ist morgen krank", „Ich hole Lena am Montag um 15:30 ab" oder „Kommt Tom beim Zoo-Ausflug mit? Ja" – er versteht das und trägt es direkt ein.',
            '❓ Frag ihn auch einfach etwas, z. B. „Wann geht Lena heute?" oder „Was gibt es heute zum Mittagessen?".',
            'ℹ️ Das geht per Direktnachricht an den Hort-Manager oder mit „/hort …" – und betrifft immer nur deine eigenen Kinder.',
        ],
    ],
    [
        'version' => '2026.07.02',
        'date' => '2026-07-02',
        'title' => 'Hausaufgaben: „keine" möglich & klarere Anzeige',
        'items' => [
            '✅ Das Hort-Team kann jetzt pro Tag oder pro Wochentag „Keine Hausaufgaben" ankreuzen – auch an Tagen, an denen sonst welche vorgesehen sind.',
            '📚 Die Hausaufgabenzeit wird auf „Heute" klarer angezeigt: als Balken genau neben den Abholzeiten, die hineinfallen.',
        ],
    ],
    [
        'version' => '2026.07.01',
        'date' => '2026-07-01',
        'title' => 'Neuer Wochenplan & Hausaufgaben auf „Heute"',
        'items' => [
            '📅 Der Abholplan zeigt jetzt die ganze Woche auf einen Blick: alle Kinder mit ihren Abholzeiten, und Essen, Aktivität, Hausaufgaben- und Ausflugszeiten erscheinen als farbige Balken genau an der richtigen Uhrzeit.',
            '📚 Auf „Heute" wird die Hausaufgabenzeit direkt in der Liste angezeigt – so fällt sofort auf, wenn ein Kind mitten in der Hausaufgabenzeit abgeholt wird. 🙌 Idee dazu von Erik – vielen Dank!',
        ],
    ],
    [
        'version' => '2026.06.28',
        'date' => '2026-06-28',
        'title' => 'Der Hort-Manager als App – mit Benachrichtigungen',
        'items' => [
            '📲 Du kannst den Hort-Manager als App installieren – auf dem iPhone über „Teilen → Zum Home-Bildschirm", auf Android über „Installieren".',
            '🔔 Aktiviere Benachrichtigungen unter Profil → Benachrichtigungen: Du erfährst sofort, wenn dein Kind abgeholt wurde oder allein gegangen ist – und wenn ein neuer Ausflug ansteht.',
            '🚌 Offene Ausflug-Abstimmungen erscheinen jetzt als kleine Zahl auf dem App-Symbol.',
        ],
    ],
];
