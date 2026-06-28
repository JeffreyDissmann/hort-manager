<script setup>
// Plain-language manual, shown on the Hilfe page (guest and logged-in).
// Written for both parents (Eltern) and staff (Erzieher:innen).

const firstSteps = [
    'Tippe auf „Mit Slack anmelden“ – dein Konto wird beim ersten Mal automatisch erstellt.',
    'Lege unter „Kinder“ dein Kind an.',
    'Trage den Stammplan ein: wann dein Kind an welchem Wochentag abgeholt wird.',
    'Fertig. Ab jetzt siehst du alles und bekommst wichtige Infos als Nachricht in Slack.',
];

const areas = [
    {
        icon: '☀️',
        title: 'Heute',
        audience: 'Für alle',
        text: 'Die Übersicht für den heutigen Tag: Wer wird wann abgeholt, wer geht allein nach Hause, wer ist auf einem Ausflug. Erzieher:innen haken jedes Kind ab, sobald es geht.',
    },
    {
        icon: '📅',
        title: 'Abholplan',
        audience: 'Für alle',
        text: 'Der Wochenplan von Montag bis Freitag mit den regulären Abholzeiten. Brauchst du an einem Tag ausnahmsweise eine andere Zeit, kannst du genau diesen Tag anpassen.',
    },
    {
        icon: '🚌',
        title: 'Ausflüge',
        audience: 'Eltern antworten',
        text: 'Steht ein Ausflug an, wirst du gefragt, ob dein Kind mitkommt. Ein Klick auf Ja oder Nein genügt – in der App oder direkt in Slack. Erzieher:innen planen die Ausflüge.',
    },
    {
        icon: '👧',
        title: 'Kinder',
        audience: 'Eltern & Erzieher:innen',
        text: 'Hier legst du dein Kind an und pflegst seinen Stammplan. Du kannst auch das zweite Elternteil verknüpfen, damit ihr beide alles seht und ändern könnt.',
    },
    {
        icon: '🍽️',
        title: 'Programm',
        audience: 'Nur Erzieher:innen',
        text: 'Erzieher:innen tragen Mittagessen, Aktivität und die Hausaufgaben-Zeiten der Woche ein. Eltern sehen das mit.',
    },
];

const slackPoints = [
    'Du bekommst eine kurze Nachricht, sobald dein Kind abgeholt wurde oder allein gegangen ist.',
    'Bei einem neuen Ausflug schickt dir der Hort-Manager alle Infos mit Ja/Nein-Knöpfen – du kannst direkt in Slack antworten.',
    'Tippe in Slack den Befehl „/hort“, um schnell in die App zu springen.',
    'Über die App „Hort-Manager“ in deiner Slack-Seitenleiste kommst du jederzeit hierher.',
];

const glossary = [
    ['Stammplan', 'Die festen, wöchentlich gleichen Abholzeiten eines Kindes – die Grundlage für den Abholplan.'],
    ['Abholplan', 'Der konkrete Plan für eine bestimmte Woche. Er kommt aus dem Stammplan, kann aber pro Tag angepasst werden.'],
    ['abgeholt / allein gegangen', 'Die zwei Arten, wie ein Kind nach Hause kommt: von jemandem abgeholt oder allein nach Hause gegangen.'],
];
</script>

<template>
    <div class="space-y-12">
        <!-- Intro -->
        <section class="space-y-3">
            <h2 class="text-2xl font-bold text-hort-navy">Willkommen beim Hort-Manager 👋</h2>
            <p class="text-hort-navy/70">
                Der Hort-Manager hilft Eltern und Erzieher:innen, gemeinsam den Überblick zu
                behalten – vor allem über das Wichtigste:
                <strong>wann und wie jedes Kind nach Hause geht</strong>. Du kannst alles bequem
                am Handy erledigen.
            </p>
        </section>

        <!-- Quick start -->
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-hort-navy">In 4 Schritten startklar</h3>
            <ol class="space-y-3 rounded-2xl bg-hort-teal/10 p-5">
                <li
                    v-for="(step, i) in firstSteps"
                    :key="i"
                    class="flex gap-3 text-sm text-hort-navy/80"
                >
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-hort-teal text-xs font-bold text-hort-navy"
                    >
                        {{ i + 1 }}
                    </span>
                    <span>{{ step }}</span>
                </li>
            </ol>
        </section>

        <!-- Login -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">Wie melde ich mich an?</h3>
            <p class="text-hort-navy/70">
                Mit <strong>„Mit Slack anmelden“</strong> – ein eigenes Passwort brauchst du nicht.
                Voraussetzung ist, dass du im Slack des Horts bist. Beim ersten Anmelden wird dein
                Konto automatisch angelegt.
            </p>
        </section>

        <!-- Areas -->
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-hort-navy">Was kann ich wo machen?</h3>
            <ul class="grid gap-3 sm:grid-cols-2">
                <li
                    v-for="area in areas"
                    :key="area.title"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-hort-navy">
                            <span class="mr-1">{{ area.icon }}</span> {{ area.title }}
                        </p>
                        <span
                            class="shrink-0 rounded-full bg-hort-navy/5 px-2 py-0.5 text-[11px] font-medium text-hort-navy/60"
                        >
                            {{ area.audience }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-hort-navy/70">{{ area.text }}</p>
                </li>
            </ul>
        </section>

        <!-- Slack -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">Was passiert in Slack?</h3>
            <p class="text-hort-navy/70">
                Der Hort-Manager ist mit dem Slack des Horts verbunden, damit du nichts verpasst:
            </p>
            <ul class="space-y-2">
                <li
                    v-for="(point, i) in slackPoints"
                    :key="i"
                    class="flex gap-2 text-sm text-hort-navy/70"
                >
                    <span class="text-hort-teal-dark">✓</span>
                    <span>{{ point }}</span>
                </li>
            </ul>
        </section>

        <!-- Install as app / notifications -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">Als App installieren & Benachrichtigungen</h3>
            <p class="text-hort-navy/70">
                Du kannst den Hort-Manager wie eine echte App auf dein Handy legen – dann
                startet er im Vollbild und kann dir Benachrichtigungen schicken (z. B.
                „Kind wurde abgeholt“ oder eine Erinnerung an einen Ausflug).
            </p>
            <ul class="space-y-2 text-sm text-hort-navy/70">
                <li><strong>iPhone (Safari):</strong> Teilen-Symbol antippen → „Zum Home-Bildschirm“.</li>
                <li><strong>Android (Chrome):</strong> oben auf das Banner „Installieren“ tippen (oder Menü → „App installieren“).</li>
                <li>Danach im Menü oben rechts auf <strong>🔔 Benachrichtigungen an</strong> tippen und erlauben.</li>
            </ul>
            <p class="text-sm text-hort-navy/60">
                Hinweis: Auf dem iPhone funktionieren Benachrichtigungen nur, wenn die App
                vorher zum Home-Bildschirm hinzugefügt wurde.
            </p>
        </section>

        <!-- Roles -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">Wer darf was?</h3>
            <ul class="space-y-2 text-sm text-hort-navy/70">
                <li><strong>Eltern</strong> sehen alles, pflegen ihre eigenen Kinder und antworten auf Ausflüge.</li>
                <li><strong>Erzieher:innen</strong> haken Abholungen ab und planen Ausflüge und das Programm.</li>
                <li><strong>Admins</strong> verwalten zusätzlich die Benutzer und vergeben die Rollen.</li>
            </ul>
        </section>

        <!-- Glossary -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">Kurz erklärt</h3>
            <dl class="space-y-3">
                <div v-for="[term, def] in glossary" :key="term" class="text-sm">
                    <dt class="font-semibold text-hort-navy">{{ term }}</dt>
                    <dd class="text-hort-navy/70">{{ def }}</dd>
                </div>
            </dl>
        </section>

        <!-- Questions -->
        <section class="space-y-2">
            <h3 class="text-lg font-semibold text-hort-navy">Noch Fragen?</h3>
            <p class="text-hort-navy/70">
                Bei Fragen oder Problemen mit der App wende dich an den Entwickler
                <strong>Jeffrey Dissmann</strong>:
                <a
                    href="mailto:jeffrey@dissmann.net"
                    class="font-medium text-hort-teal-dark underline hover:text-hort-navy"
                >jeffrey@dissmann.net</a>.
            </p>
        </section>
    </div>
</template>
