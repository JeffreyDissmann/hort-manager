<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/** Answers a parent's question, grounded only in the context it's given. */
class HortAnswerAgent implements Agent
{
    use Promptable;

    public function __construct(private string $context) {}

    /** Cap the Ollama request so a hung host can't block the queue worker. */
    public function timeout(): int
    {
        return (int) config('ai.providers.ollama.request_timeout', 30);
    }

    public function instructions(): string
    {
        return <<<PROMPT
        Du bist ein freundlicher Assistent einer Hort-App. Beantworte die Frage der Eltern KURZ auf Deutsch, ausschließlich anhand der folgenden Daten. Steht die Antwort nicht in den Daten, sage das ehrlich.

        Antworte in einfachem Fließtext für Slack. KEIN Markdown: keine Rauten (#), keine doppelten Sternchen (**), keine Klammer-Links [Text](URL), keine Aufzählungszeichen mit „-“. Für Fettdruck höchstens *ein* Sternchen, für Listen das Zeichen „•“.

        Daten:
        {$this->context}
        PROMPT;
    }
}
