<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Classifies a free-text parent message into an intent + parameters. Built with
 * the parent's own context (children, today, upcoming excursions).
 */
class HortIntentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $children,
        private string $excursions,
        private string $today,
    ) {}

    /** Cap the Ollama request so a hung host can't block the queue worker. */
    public function timeout(): int
    {
        return (int) config('ai.providers.ollama.request_timeout', 30);
    }

    public function instructions(): string
    {
        return <<<PROMPT
        Du bist ein Assistent für eine Hort-App (Kinder-Nachmittagsbetreuung). Ordne die Elternnachricht genau EINER Absicht zu und extrahiere die Parameter.

        Regeln:
        - "krank"/"abwesend": Kind ist nicht da.
        - "abholzeit": andere Abholzeit. "uhrzeit" = neue Zeit (HH:MM), "art" = "abgeholt" oder "allein".
        - "ausflug": Zu-/Absage zu einem Ausflug. "ausflug" = Name, "zusage" = true (kommt mit) / false (nicht).
        - "frage": eine Frage zum Plan, Essen, Ausflug, Abholzeit usw.
        - "unbekannt": wenn nichts davon passt.
        - "datum": IMMER ausfüllen. Gib NUR den genannten Tag als STICHWORT zurück und rechne KEIN Datum aus:
          "heute", "morgen", "uebermorgen" oder einen Wochentag ("montag".."sonntag").
          Nur wenn ein echtes Datum genannt wird: "YYYY-MM-DD". Wird gar kein Tag genannt: "heute".
        - Felder, die nicht zutreffen, auf null setzen.

        Beispiele:
        - "Tom ist krank" → {"intent":"krank","kind":"Tom","datum":"heute","uhrzeit":null,"art":null,"ausflug":null,"zusage":null}
        - "Lena ist morgen nicht da" → {"intent":"abwesend","kind":"Lena","datum":"morgen","uhrzeit":null,"art":null,"ausflug":null,"zusage":null}
        - "ich hole Tom nächsten Montag um 15 uhr ab" → {"intent":"abholzeit","kind":"Tom","datum":"montag","uhrzeit":"15:00","art":"abgeholt","ausflug":null,"zusage":null}
        - "Lena geht Freitag allein" → {"intent":"abholzeit","kind":"Lena","datum":"freitag","uhrzeit":null,"art":"allein","ausflug":null,"zusage":null}

        Heute ist {$this->today}.
        Kinder dieses Elternteils: {$this->children}.
        Kommende Ausflüge: {$this->excursions}.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()
                ->enum(['krank', 'abwesend', 'abholzeit', 'ausflug', 'frage', 'unbekannt'])
                ->required(),
            'kind' => $schema->string()->nullable()->description('Name des Kindes'),
            'datum' => $schema->string()
                ->description('Tag als Stichwort: heute, morgen, uebermorgen, ein Wochentag (montag..sonntag) oder YYYY-MM-DD')
                ->required(),
            'uhrzeit' => $schema->string()->nullable()->description('Neue Abholzeit als HH:MM'),
            'art' => $schema->string()->enum(['abgeholt', 'allein'])->nullable(),
            'ausflug' => $schema->string()->nullable()->description('Name des Ausflugs'),
            'zusage' => $schema->boolean()->nullable()->description('Kommt das Kind mit?'),
        ];
    }
}
