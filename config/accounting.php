<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI draft suggestions
    |--------------------------------------------------------------------------
    |
    | When enabled, imported bank-statement drafts are run through the local
    | Ollama model to suggest a category and counterparty before review. Disable
    | to import without the AI pass (e.g. when Ollama is unreachable).
    |
    */

    'ai_suggestions' => (bool) env('ACCOUNTING_AI_SUGGESTIONS', true),

];
