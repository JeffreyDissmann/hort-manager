<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Picks the Paperless document that best matches one booking. The caller passes the
 * shortlist of candidate documents (already full-text-ranked by Paperless) and prompts
 * with the booking's key fields; the model returns the single best document id, or null
 * when none convincingly fits. Runs on the same local Ollama model as the app's other
 * AI features.
 */
class PaperlessMatcher implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<array{id:int, title:string, created:?string}>  $candidates
     */
    public function __construct(private readonly array $candidates) {}

    /** Cap the Ollama request so a hung host can't block the queue. */
    public function timeout(): int
    {
        return (int) config('ai.providers.ollama.request_timeout', 30);
    }

    public function instructions(): string
    {
        $candidates = collect($this->candidates)
            ->map(fn (array $c): string => "  {$c['id']} · ".($c['created'] ?? '?')." · {$c['title']}")
            ->implode("\n");

        return <<<TXT
        Du ordnest einer Buchhaltungs-Buchung den passenden Beleg (Rechnung, Kassenbon,
        Quittung) aus dem Dokumentenarchiv Paperless zu.

        Regeln:
        - Wähle nur eine Dokument-ID aus der Liste unten. Erfinde niemals IDs.
        - Der Beleg muss zur Buchung passen: Händler/Zweck ähnlich UND das Belegdatum
          liegt nahe am Buchungsdatum (meist wenige Tage davor).
        - Passt kein Dokument überzeugend, gib document_id = null zurück. Rate nicht.
        - Schätze deine Sicherheit ehrlich ein:
          „high": Händler/Zweck und Datum passen eindeutig.
          „medium": Plausibel, aber nicht sicher.
          „low": Unsicher.
          Sei bei „high" streng – nur bei einem klaren Treffer.

        Kandidaten-Dokumente (id · Datum · Titel):
        {$candidates}

        Gib genau ein Ergebnis-Objekt für die eine übergebene Buchung zurück.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->nullable()->description('ID des passenden Dokuments aus der Liste oder null'),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->nullable()->description('Wie sicher ist die Zuordnung? low, medium oder high'),
        ];
    }
}
