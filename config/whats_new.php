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
        'version' => '2026.07.10',
        'date' => '2026-07-10',
        'title' => 'Stammplan: „Hortfrei" & Uhrzeit-Angabe 🗓️',
        'items' => [
            '🗓️ Im Stammplan wählst du pro Tag jetzt klar: „Hortfrei", „Wird abgeholt" oder „Geht allein" – kein leeres Feld mehr. Und du siehst auf „Heute" und im Wochenplan, wer regulär nicht da ist.',
            '🕒 Bei „Geht allein" kannst du auch im Stammplan (und direkt auf „Heute") sagen, ob die Uhrzeit „bis", „genau um" oder „ab" gemeint ist.',
            '⚠️ Fehlt für dein Kind noch ein Wochenplan, erinnert dich künftig ein Hinweis oben – mit direktem Link zum Eintragen.',
        ],
    ],
    [
        'version' => '2026.07.09',
        'date' => '2026-07-09',
        'title' => 'Zum Aktualisieren nach unten ziehen 🔄',
        'items' => [
            '🔄 Zieh „Heute" oder den Wochenplan nach unten, um zu aktualisieren – wie in Social-Apps. Die App holt sich neue Infos außerdem von selbst, wenn du sie nach einer Weile wieder öffnest.',
            '🤒 „Krank melden" / „Kommt nicht" auf „Heute" fragt jetzt kurz nach dem Grund – und du kannst es direkt wieder aufheben.',
            '🚌 Bei den Ausflügen stehen die Kinder jetzt sortiert: erst „dabei", dann „noch offen", dann „nicht dabei". Das Hort-Team sieht die ganze Liste immer aufgeklappt.',
        ],
    ],
    [
        'version' => '2026.07.08',
        'date' => '2026-07-08',
        'title' => 'Mit einem anderen Kind nach Hause 🧒🧒',
        'items' => [
            '🧒 Neu im Wochenplan: „Geht mit einem anderen Kind mit". Wähl das Kind aus – die Abholzeit wird automatisch übernommen und bleibt mit ihm verknüpft.',
            '🙋 Geht das andere Kind allein nach Hause, wird dessen Familie um Erlaubnis gefragt (per Slack oder direkt in der App). Wird es abgeholt, ist alles sofort erledigt.',
            '🕒 Bei „Geht allein" kannst du jetzt sagen, ob die Uhrzeit „bis", „genau um" oder „ab" gemeint ist.',
            '📋 Oben auf „Heute" und im Wochenplan siehst du eine Übersicht „Mit anderen nach Hause" – mit dem Stand der Zusage.',
            '🤒 Meldest du dein Kind krank oder als „kommt nicht", brauchen wir jetzt einen kurzen Grund dazu.',
            '🙌 Idee dazu von Andrea, Ezgi und Vio – vielen Dank!',
        ],
    ],
    [
        'version' => '2026.07.07',
        'date' => '2026-07-07',
        'title' => 'Dunkelmodus 🌙 & übersichtlichere Pläne',
        'items' => [
            '🌙 Der Hort-Manager gibt es jetzt auch in Dunkel – angenehmer für die Augen am Abend.',
            '⚙️ Stell es unter „Profil → Darstellung" ein: Hell, Dunkel oder „Automatisch" (folgt der Einstellung deines Geräts).',
            'ℹ️ Die Auswahl gilt pro Gerät – so kann dein Handy dunkel und dein Rechner hell sein.',
            '👀 Wochenplan und Stammplan sind klarer: Wer allein nach Hause geht, ist jetzt deutlich zu erkennen (🚶 und eigene Farbe), der Name ist kräftiger und die Schrift etwas größer.',
            '🙌 Idee zum Dunkelmodus von Stepan!',
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
