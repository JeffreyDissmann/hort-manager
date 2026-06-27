<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Excursion;
use App\Models\User;
use App\Services\SlackRsvp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class SlackInteractionController extends Controller
{
    /**
     * Handle a Slack interactive button click. Buttons carry a value of
     * "rsvp|{excursionId}|{childId}|{1|0}". The request is already signature-verified.
     */
    public function handle(Request $request): Response
    {
        $payload = json_decode((string) $request->input('payload'), true) ?? [];
        $responseUrl = data_get($payload, 'response_url');

        [$tag, $excursionId, $childId, $answer] = array_pad(
            explode('|', (string) data_get($payload, 'actions.0.value')), 4, null,
        );

        if ($tag !== 'rsvp') {
            return response()->noContent();
        }

        $user = User::firstWhere('slack_id', data_get($payload, 'user.id'));
        $excursion = Excursion::find($excursionId);
        $child = Child::find($childId);

        if (! $user || ! $excursion || ! $child || ! $child->isGuardedBy($user) || ! $excursion->pollIsOpen()) {
            $this->reply($responseUrl, '⚠️ Diese Abstimmung ist nicht (mehr) möglich.');

            return response()->noContent();
        }

        $excursion->children()->syncWithoutDetaching([
            $child->id => [
                'response' => (bool) $answer,
                'answered_by' => $user->id,
                'answered_at' => now(),
            ],
        ]);

        // Re-render every guardian's DM: this child's row now shows the result.
        app(SlackRsvp::class)->syncForChild($excursion, $child);

        return response()->noContent();
    }

    /** Post a confirmation back into the same Slack DM via the interaction's response_url. */
    private function reply(?string $responseUrl, string $text): void
    {
        if ($responseUrl) {
            Http::post($responseUrl, ['text' => $text]);
        }
    }
}
