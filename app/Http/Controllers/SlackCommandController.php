<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SlackCommandController extends Controller
{
    /**
     * The /hort slash command. With no argument it replies with quick links; with
     * `krank`/`abwesend [Name]` it reports the caller's child as away for today.
     */
    public function handle(Request $request): JsonResponse
    {
        $parts = preg_split('/\s+/', trim((string) $request->input('text', '')), 2) ?: [];
        $command = strtolower($parts[0] ?? '');
        $name = trim($parts[1] ?? '');

        return match ($command) {
            'krank', 'sick' => $this->reportAbsence($request, AbsenceReason::Sick, $name),
            'abwesend', 'away' => $this->reportAbsence($request, AbsenceReason::Away, $name),
            default => $this->quickLinks(),
        };
    }

    private function reportAbsence(Request $request, AbsenceReason $reason, string $name): JsonResponse
    {
        $keyword = $reason === AbsenceReason::Sick ? 'krank' : 'abwesend';

        $user = User::firstWhere('slack_id', $request->input('user_id'));
        if (! $user) {
            return $this->ephemeral('Bitte melde dich zuerst einmal in der App an (👋 „Mit Slack anmelden“).');
        }

        $children = $user->children;
        if ($children->isEmpty()) {
            return $this->ephemeral('Dir ist noch kein Kind zugeordnet.');
        }

        $child = $this->matchChild($children, $name);
        if (! $child) {
            $names = $children->pluck('name')->implode(', ');

            return $this->ephemeral("Für welches Kind? Tippe `/hort {$keyword} <Name>` – deine Kinder: {$names}.");
        }

        Absence::report($child, now()->toDateString(), $reason, $user->id);

        return $this->ephemeral("✅ *{$child->name}* ist für heute als „{$reason->label()}“ gemeldet.");
    }

    /**
     * Match a single child by name (or the only child when no name is given).
     *
     * @param  Collection<int, Child>  $children
     */
    private function matchChild(Collection $children, string $name): ?Child
    {
        if ($name === '') {
            return $children->count() === 1 ? $children->first() : null;
        }

        $needle = mb_strtolower($name);
        $matches = $children->filter(fn (Child $c) => str_contains(mb_strtolower($c->name), $needle));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function ephemeral(string $text): JsonResponse
    {
        return response()->json(['response_type' => 'ephemeral', 'text' => $text]);
    }

    /** Quick links into the app (the default /hort reply). */
    private function quickLinks(): JsonResponse
    {
        return response()->json([
            'response_type' => 'ephemeral',
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '👋 *Hort-Manager* – tippe auf einen Bereich, oder melde dein Kind krank mit `/hort krank <Name>`:']],
                [
                    'type' => 'actions',
                    'elements' => [
                        $this->link('🏠 Heute', 'board'),
                        $this->link('🚌 Ausflüge', 'polls'),
                        $this->link('👧 Kinder', 'children'),
                    ],
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function link(string $text, string $to): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'url' => route('slack.enter', ['to' => $to]),
        ];
    }
}
