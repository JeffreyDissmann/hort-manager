<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Jobs\SyncExcursionRsvp;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Support\CompanionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class SlackInteractionController extends Controller
{
    /**
     * Handle a Slack interactive button click. Buttons carry a pipe-delimited value:
     * "rsvp|{excursionId}|{childId}|{1|0}" or "companion|{departureId}|{1|0}". The
     * request is already signature-verified by middleware.
     */
    public function handle(Request $request): Response
    {
        $payload = json_decode((string) $request->input('payload'), true) ?? [];
        $responseUrl = data_get($payload, 'response_url');
        $value = (string) data_get($payload, 'actions.0.value');
        $user = User::firstWhere('slack_id', data_get($payload, 'user.id'));

        match (strtok($value, '|')) {
            'rsvp' => $this->answerRsvp($value, $user, $responseUrl),
            'companion' => $this->answerCompanion($value, $user, $responseUrl),
            default => null,
        };

        return response()->noContent();
    }

    private function answerRsvp(string $value, ?User $user, ?string $responseUrl): void
    {
        [, $excursionId, $childId, $answer] = array_pad(explode('|', $value), 4, null);

        $excursion = Excursion::find($excursionId);
        $child = Child::find($childId);

        if (! $user || ! $excursion || ! $child || ! $child->isGuardedBy($user) || ! $excursion->pollIsOpen()) {
            $this->reply($responseUrl, '⚠️ Diese Abstimmung ist nicht (mehr) möglich.');

            return;
        }

        $excursion->children()->syncWithoutDetaching([
            $child->id => [
                'response' => (bool) $answer,
                'answered_by' => $user->id,
                'answered_at' => now(),
            ],
        ]);

        // Log it just like an in-app answer (polls.update) so the Protokoll shows
        // every RSVP, not only the ones made in the app.
        activity()
            ->causedBy($user)
            ->performedOn($excursion)
            ->event((bool) $answer ? 'rsvp_yes' : 'rsvp_no')
            ->log($child->name.' · '.$excursion->name);

        // Re-render every guardian's DM (queued) so Slack gets a fast ack.
        SyncExcursionRsvp::dispatch($excursion, $child);
    }

    private function answerCompanion(string $value, ?User $user, ?string $responseUrl): void
    {
        [, $departureId, $answer] = array_pad(explode('|', $value), 3, null);

        $departure = DailyDeparture::find($departureId);

        // Only the companion's guardian may answer, and only a real, open arrangement.
        $valid = $user
            && $departure
            && $departure->planned_method === DepartureMethod::WithChild
            && $departure->companion_child_id
            && ($departure->companion?->isGuardedBy($user) ?? false);

        if (! $valid) {
            $this->reply($responseUrl, '⚠️ Diese Anfrage ist nicht (mehr) möglich.');

            return;
        }

        CompanionAnswer::record($departure, (bool) (int) $answer, $user->id);
    }

    /** Post a confirmation back into the same Slack DM via the interaction's response_url. */
    private function reply(?string $responseUrl, string $text): void
    {
        // Only ever post back to Slack's own webhook host. Bounded timeout: this
        // runs inside the request that must ack Slack within 3s.
        if ($responseUrl && str_starts_with($responseUrl, 'https://hooks.slack.com/')) {
            Http::connectTimeout(3)->timeout(5)->post($responseUrl, ['text' => $text]);
        }
    }
}
