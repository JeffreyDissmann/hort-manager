<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How confident an AI booking suggestion is — a review-triage signal, not an
 * auto-confirm. Backed by a small int so the value doubles as the sort rank
 * (0 = riskiest): ordering is a plain `orderBy('confidence')`.
 */
enum SuggestionConfidence: int
{
    case Low = 0;
    case Medium = 1;
    case High = 2;

    public function label(): string
    {
        return __('enums.suggestion_confidence.'.$this->value);
    }
}
