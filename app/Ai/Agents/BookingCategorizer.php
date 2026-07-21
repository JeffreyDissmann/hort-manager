<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Suggests a category and counterparty for a single imported bank-statement line.
 * The category tree, children (with guardians) and users are passed in as context;
 * the caller prompts with one row and receives one suggestion. One call per booking
 * keeps the small local Ollama model reliable and lets each row be queued/retried
 * on its own. Runs on the same Ollama model as the rest of the app's AI features.
 */
class BookingCategorizer implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<array{id:int, path:string, direction:string, comment?:?string}>  $categories
     * @param  list<array{id:int, name:string, guardians:string}>  $children
     * @param  list<array{id:int, name:string}>  $users
     */
    public function __construct(
        private array $categories,
        private array $children,
        private array $users,
    ) {}

    /** Cap the Ollama request so a hung host can't block the request. */
    public function timeout(): int
    {
        return (int) config('ai.providers.ollama.request_timeout', 30);
    }

    public function instructions(): string
    {
        $categories = collect($this->categories)
            ->map(function (array $c): string {
                $line = "  {$c['id']} · {$c['direction']} · {$c['path']}";

                return ! empty($c['comment']) ? $line.' — '.$c['comment'] : $line;
            })
            ->implode("\n");

        $children = collect($this->children)
            ->map(fn (array $c): string => "  {$c['id']} · {$c['name']} (Eltern: {$c['guardians']})")
            ->implode("\n");

        $users = collect($this->users)
            ->map(fn (array $u): string => "  {$u['id']} · {$u['name']}")
            ->implode("\n");

        return <<<TXT
        Du ordnest Kontoauszug-Zeilen für einen Hort (Kinder-Nachmittagsbetreuung) Kategorien zu.
        Wähle für jede Eingabezeile die beste Kategorie und erkenne die Gegenpartei (von wem/an wen).

        Regeln:
        - Verwende nur Kategorie-IDs aus der Liste unten. Erfinde niemals IDs.
        - Wähle immer die SPEZIFISCHSTE passende Kategorie (unterste Ebene / Blatt).
          Eine Oberkategorie nur, wenn wirklich keine Unterkategorie passt (der Pfad
          zeigt die Ebene mit „ › ").
        - WICHTIG: Steht ein Kategoriename wörtlich im Verwendungszweck (z. B.
          „Vereinsbeitrag", „Essensgeld", „Elternbeitrag", „Kaution"), wähle GENAU
          diese Kategorie – auch wenn ein Name oder anderes Wort auf etwas anderes
          hindeutet. Der Verwendungszweck-Text hat Vorrang.
        - Ein positiver Betrag ist eine Einnahme und braucht eine Einnahme-Kategorie;
          ein negativer Betrag ist eine Ausgabe und braucht eine Ausgabe-Kategorie.
        - EINNAHMEN (z. B. Essensgeld, Elternbeitrag, Vereinsbeitrag) werden dem KIND
          zugeordnet, nicht dem Elternteil. Eltern nennen im Verwendungszweck oft den
          Namen des Kindes ODER überweisen unter dem Namen eines Elternteils – nutze
          die Eltern-Zuordnung unten, um das richtige Kind zu finden. Gib dann
          counterparty_child_id zurück.
        - AUSGABEN an eine Person (z. B. Gehalt) passen zu einem bekannten Benutzer –
          gib counterparty_user_id zurück. Sonst gib einen bereinigten
          counterparty_name zurück (Firma/Vermietung) oder lass es weg, wenn unklar.
        - Bist du bei der Kategorie unsicher, lass category_id weg statt zu raten.
        - Deutsche Verwendungszwecke sind unübersichtlich; achte auf Abkürzungen
          wie SEPA-GUTSCHRIFT, DAUERAUFTRAG, Verwendungszweck, Empfaenger.

        Kategorien (id · Richtung · Pfad — optionaler Hinweis zur Bedeutung):
        {$categories}

        Kinder (id · Name · Eltern):
        {$children}

        Bekannte Benutzer (id · Name):
        {$users}

        Schätze außerdem ehrlich deine Sicherheit ein (confidence):
        - „high": Die Kategorie ist eindeutig – ein Händler, Stichwort oder Name im
          Verwendungszweck passt klar zu genau dieser Kategorie.
        - „medium": Plausibel, aber es käme auch eine andere Kategorie in Frage.
        - „low": Du rätst oder findest keine passende Kategorie.
        Sei bei „high" ehrlich – lieber „medium", wenn du nicht sicher bist.

        Gib genau ein Vorschlags-Objekt für die eine übergebene Buchung zurück.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->nullable()->description('ID aus der Kategorienliste oder null'),
            'counterparty_child_id' => $schema->integer()->nullable()->description('Bei Einnahmen: ID des zugeordneten Kindes oder null'),
            'counterparty_user_id' => $schema->integer()->nullable()->description('Bei Ausgaben an eine Person: ID eines bekannten Benutzers oder null'),
            'counterparty_name' => $schema->string()->nullable()->description('Bereinigter Name der Gegenpartei (Firma) oder null'),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->nullable()->description('Wie sicher bist du bei dieser Zuordnung? low, medium oder high'),
        ];
    }
}
