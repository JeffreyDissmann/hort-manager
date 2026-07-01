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
        'version' => '2026.07.01',
        'date' => '2026-07-01',
        'title' => 'Hausaufgabenzeit auf der Heute-Seite',
        'items' => [
            '📚 Die Hausaufgabenzeit wird jetzt direkt in der Heute-Liste angezeigt – an der richtigen Uhrzeit zwischen den Abholzeiten. So fällt sofort auf, wenn ein Kind mitten in der Hausaufgabenzeit abgeholt wird.',
            '🙌 Idee und Anstoß dazu von Erik – vielen Dank!',
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
