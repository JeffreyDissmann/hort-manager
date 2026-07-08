<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\HortAnswerAgent;
use App\Ai\Agents\HortIntentAgent;
use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Jobs\SyncExcursionRsvp;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Support\CompanionReconciler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;

/**
 * Turns a free-text parent message (Slack DM or /hort) into an action, using a
 * local LLM to classify the intent and extract parameters. Everything is scoped
 * to the parent's own children.
 */
class HortAssistant
{
    private const WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr'];

    public function reply(User $user, string $text): string
    {
        $children = $user->children;
        if ($children->isEmpty()) {
            return 'Dir ist noch kein Kind zugeordnet.';
        }

        $intent = $this->classify($children, $text);
        if ($intent === null) {
            return 'Der Assistent ist gerade nicht verfügbar. Versuch es später noch einmal.';
        }

        return match ($intent['intent'] ?? 'unbekannt') {
            'krank' => $this->reportAbsence($user, $children, $intent, AbsenceReason::Sick),
            'abwesend' => $this->reportAbsence($user, $children, $intent, AbsenceReason::Away),
            'abholzeit' => $this->changePickup($children, $intent),
            'ausflug' => $this->rsvp($user, $children, $intent),
            'frage' => $this->answer($children, $text),
            default => $this->help(),
        };
    }

    /**
     * Classify a message into an intent + params via the LLM; null on failure.
     *
     * @param  Collection<int, Child>  $children
     * @return array<string, mixed>|null
     */
    private function classify(Collection $children, string $text): ?array
    {
        $today = now();
        $excursions = Excursion::whereDate('date', '>=', $today->toDateString())
            ->orderBy('date')->pluck('name')->implode(', ');

        try {
            $response = (new HortIntentAgent(
                $children->pluck('name')->implode(', '),
                $excursions !== '' ? $excursions : '—',
                $today->format('Y-m-d').' ('.$this->weekdayLong($today).')',
            ))->prompt($text, provider: Lab::Ollama, model: (string) config('ai.providers.ollama.model'));

            return $response->structured;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @param Collection<int, Child> $children */
    private function reportAbsence(User $user, Collection $children, array $intent, AbsenceReason $reason): string
    {
        $child = $this->matchChild($children, $intent['kind'] ?? null);
        if (! $child) {
            return $this->whichChild($children);
        }

        $date = $this->resolveDate($intent['datum'] ?? null);
        if (! $date) {
            return 'Für welchen Tag? Bitte „heute“, „morgen“ oder ein Datum angeben.';
        }

        Absence::report($child, $date, $reason, $user->id);

        return "✅ *{$child->name}* ist {$this->humanDate($date)} als „{$reason->label()}“ gemeldet.";
    }

    /** @param Collection<int, Child> $children */
    private function changePickup(Collection $children, array $intent): string
    {
        $child = $this->matchChild($children, $intent['kind'] ?? null);
        if (! $child) {
            return $this->whichChild($children);
        }

        $date = $this->resolveDate($intent['datum'] ?? null);
        if (! $date) {
            return 'Für welchen Tag soll ich die Abholzeit ändern?';
        }

        $time = $intent['uhrzeit'] ?? null;
        if ($time !== null && ! preg_match('/^\d{2}:\d{2}$/', (string) $time)) {
            $time = null;
        }

        $method = match ($intent['art'] ?? null) {
            'allein' => DepartureMethod::SentHome,
            'abgeholt' => DepartureMethod::PickedUp,
            default => null,
        };

        if ($time === null && $method === null) {
            return "Wie soll ich die Abholung von *{$child->name}* {$this->humanDate($date)} ändern? Bitte eine Uhrzeit oder „abgeholt“/„allein“ angeben.";
        }

        // Only overwrite what the parent actually specified — a method change
        // („wird abgeholt") must not wipe an existing planned time, and vice versa.
        $departure = DailyDeparture::firstOrNew(['child_id' => $child->id, 'date' => $date]);
        if ($time !== null) {
            $departure->planned_time = $time;
        }
        if ($method !== null) {
            $departure->planned_method = $method;
        }
        if (! $departure->exists) {
            $departure->status = DepartureStatus::Present;
        }
        // The assistant sets a concrete pickup, so any „geht mit … mit" arrangement no
        // longer applies — clear it (the assistant can't create one).
        $departure->companion_child_id = null;
        $departure->companion_confirmed = null;
        $departure->companion_confirmed_by = null;
        $departure->companion_confirmed_at = null;
        $departure->save();

        // This child may be another child's companion — re-evaluate those arrangements.
        CompanionReconciler::reconcile($child->id, $date);

        $how = match (true) {
            $time !== null && $method !== null => "um {$time} Uhr ({$method->label()})",
            $time !== null => "um {$time} Uhr",
            default => $method->label(),
        };

        return "✅ *{$child->name}* {$this->humanDate($date)}: {$how}.";
    }

    /** @param Collection<int, Child> $children */
    private function rsvp(User $user, Collection $children, array $intent): string
    {
        $child = $this->matchChild($children, $intent['kind'] ?? null);
        if (! $child) {
            return $this->whichChild($children);
        }

        $attending = $intent['zusage'] ?? null;
        if (! is_bool($attending)) {
            return 'Kommt dein Kind mit oder nicht? Bitte „ja“ oder „nein“ dazuschreiben.';
        }

        $excursion = $this->findExcursion($child, $intent['ausflug'] ?? null);
        if (! $excursion) {
            return 'Zu welchem Ausflug? Ich finde gerade keinen passenden.';
        }
        if (! $excursion->pollIsOpen()) {
            return "Die Abstimmung für „{$excursion->name}“ ist bereits geschlossen.";
        }

        $excursion->children()->syncWithoutDetaching([
            $child->id => ['response' => $attending, 'answered_by' => $user->id, 'answered_at' => now()],
        ]);
        SyncExcursionRsvp::dispatch($excursion, $child);

        return "✅ *{$child->name}* ".($attending ? 'kommt' : 'kommt nicht')." beim Ausflug „{$excursion->name}“ mit.";
    }

    /** @param Collection<int, Child> $children */
    private function answer(Collection $children, string $question): string
    {
        try {
            $response = (new HortAnswerAgent($this->context($children)))
                ->prompt($question, provider: Lab::Ollama, model: (string) config('ai.providers.ollama.model'));

            $text = $this->toSlackMarkdown(trim((string) $response));

            return $text !== '' ? $text : 'Das kann ich gerade nicht beantworten.';
        } catch (\Throwable $e) {
            report($e);

            return 'Das kann ich gerade nicht beantworten.';
        }
    }

    /**
     * Rewrite the common GitHub-flavoured Markdown an LLM tends to emit into
     * Slack's mrkdwn, so nothing renders as literal `**`, `##` or `[a](b)`.
     */
    private function toSlackMarkdown(string $text): string
    {
        // [label](url) → <url|label>
        $text = (string) preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<$2|$1>', $text);
        // **bold** / __bold__ → *bold*; ~~strike~~ → ~strike~
        $text = (string) preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
        $text = (string) preg_replace('/__(.+?)__/s', '*$1*', $text);
        $text = (string) preg_replace('/~~(.+?)~~/s', '~$1~', $text);

        // Line-leading heading markers and list bullets.
        $lines = array_map(function (string $line): string {
            $line = (string) preg_replace('/^\s{0,3}#{1,6}\s+/', '', $line); // "## Titel" → "Titel"
            $line = (string) preg_replace('/^(\s*)[-*]\s+/', '$1• ', $line);  // "- x" / "* x" → "• x"

            return $line;
        }, preg_split('/\r\n|\r|\n/', $text) ?: [$text]);

        return implode("\n", $lines);
    }

    /** Compact snapshot of the parent's world for question answering. */
    private function context(Collection $children): string
    {
        $today = now();
        $lines = ['Heute: '.$this->weekdayLong($today).', '.$today->format('d.m.Y').'.'];

        // The standard weekly plan is the baseline; deviations are listed separately.
        $plans = []; // child id => [weekday => WeeklySchedule]
        foreach ($children as $child) {
            $byWeekday = $child->weeklySchedules()->get()->keyBy('weekday');
            $plans[$child->id] = $byWeekday;
            $plan = collect(self::WEEKDAYS)->map(function (string $lbl, int $wd) use ($byWeekday) {
                $s = $byWeekday->get($wd);

                return $lbl.' '.($s && $s->planned_time
                    ? substr((string) $s->planned_time, 0, 5).' ('.($s->method?->label() ?? '').')'
                    : 'frei');
            })->implode(', ');
            $lines[] = "Abholplan {$child->name} (Standard): {$plan}.";
        }

        // Upcoming deviations from that standard (next 14 days): same-day/future
        // pickup overrides and reported absences. This is what "am Freitag?" and
        // "was ist diese Woche anders?" hinge on — not just today.
        $lines = array_merge($lines, $this->upcomingDeviations($children, $plans));

        $program = DailyProgram::where('date', $today->toDateString())->first();
        $default = HomeworkDefault::where('weekday', $today->dayOfWeekIso)->first();
        [$hwStart, $hwEnd] = DailyProgram::effectiveHomework($program, $default);
        $lines[] = 'Heute: Mittagessen '.($program?->lunch ?: '—')
            .', Aktivität '.($program?->activity ?: '—')
            .', Hausaufgaben '.($hwStart ? substr((string) $hwStart, 0, 5).'–'.substr((string) $hwEnd, 0, 5) : 'keine').'.';

        $excursions = Excursion::whereDate('date', '>=', $today->toDateString())->orderBy('date')->get();
        foreach ($excursions as $e) {
            $lines[] = "Ausflug „{$e->name}“ am ".$e->date->format('d.m.Y')
                .($e->depart_at ? ' ('.substr((string) $e->depart_at, 0, 5).'–'.substr((string) $e->return_at, 0, 5).' Uhr)' : '').'.';
        }

        return implode("\n", $lines);
    }

    /**
     * Pickup overrides that deviate from the Stammplan, plus reported absences, for
     * the next two weeks — the exceptions the standard plan can't answer on its own.
     *
     * @param  Collection<int, Child>  $children
     * @param  array<int, Collection<int, WeeklySchedule>>  $plans
     * @return list<string>
     */
    private function upcomingDeviations(Collection $children, array $plans): array
    {
        $today = now();
        $from = $today->toDateString();
        $to = $today->copy()->addDays(14)->toDateString();
        $ids = $children->pluck('id');
        $nameOf = fn (int $id): string => (string) $children->firstWhere('id', $id)?->name;

        $deviations = collect();

        Absence::whereIn('child_id', $ids)->whereBetween('date', [$from, $to])->get()
            ->each(function (Absence $a) use ($deviations, $nameOf): void {
                $deviations->push([$a->date->toDateString(), $nameOf($a->child_id).' ist '.$a->reason->label()]);
            });

        DailyDeparture::whereIn('child_id', $ids)->whereBetween('date', [$from, $to])->get()
            ->each(function (DailyDeparture $d) use ($deviations, $nameOf, $plans): void {
                $date = Carbon::parse((string) $d->date);
                $standard = $plans[$d->child_id]->get($date->dayOfWeekIso);
                $sameTime = substr((string) $d->planned_time, 0, 5) === substr((string) $standard?->planned_time, 0, 5);
                if ($sameTime && $d->planned_method === $standard?->method) {
                    return; // matches the Stammplan — not a deviation
                }

                $how = $d->planned_time
                    ? 'um '.substr((string) $d->planned_time, 0, 5).' Uhr'.($d->planned_method ? ' ('.$d->planned_method->label().')' : '')
                    : 'keine feste Abholzeit';
                $deviations->push([$date->toDateString(), $nameOf($d->child_id).' '.$how]);
            });

        if ($deviations->isEmpty()) {
            return [];
        }

        return $deviations->sortBy(0)->values()
            ->map(fn (array $d): string => '- '.$this->humanDate($d[0]).': '.$d[1].'.')
            ->prepend('Abweichungen vom Standard (heute bis in zwei Wochen):')
            ->all();
    }

    /** @param Collection<int, Child> $children */
    private function matchChild(Collection $children, ?string $name): ?Child
    {
        if (! $name) {
            return $children->count() === 1 ? $children->first() : null;
        }

        $needle = mb_strtolower($name);
        $matches = $children->filter(fn (Child $c) => str_contains(mb_strtolower($c->name), $needle));

        // Exactly one match wins. A named-but-unmatched (or ambiguous) child must
        // NOT silently fall back to the only kid — that could act on the wrong
        // child; return null so the caller asks which child is meant.
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findExcursion(Child $child, ?string $name): ?Excursion
    {
        $upcoming = $child->excursions()
            ->whereDate('excursions.date', '>=', now()->toDateString())
            ->orderBy('excursions.date')
            ->get();

        if ($name) {
            $needle = mb_strtolower($name);
            $match = $upcoming->first(fn (Excursion $e) => str_contains(mb_strtolower($e->name), $needle));
            if ($match) {
                return $match;
            }
        }

        return $upcoming->count() === 1 ? $upcoming->first() : null;
    }

    /**
     * Resolve the day-keyword the model extracted into a concrete date. The LLM
     * only tags the day ("morgen", "montag", …); the arithmetic happens here,
     * because small models compute weekday→date unreliably. Past dates → null.
     */
    private function resolveDate(?string $datum): ?string
    {
        $today = now();
        if (! $datum) {
            return $today->toDateString();
        }

        $token = mb_strtolower(trim($datum));

        $relative = match ($token) {
            'heute' => $today,
            'morgen' => $today->copy()->addDay(),
            'uebermorgen', 'übermorgen' => $today->copy()->addDays(2),
            default => null,
        };
        if ($relative) {
            return $relative->toDateString();
        }

        $weekdays = [
            'montag' => 1, 'dienstag' => 2, 'mittwoch' => 3, 'donnerstag' => 4,
            'freitag' => 5, 'samstag' => 6, 'sonntag' => 7,
        ];
        foreach ($weekdays as $name => $iso) {
            if (str_contains($token, $name)) {
                // A named weekday always means the upcoming one; same weekday as
                // today rolls to next week (they'd say „heute“ otherwise).
                $ahead = ($iso - $today->dayOfWeekIso + 7) % 7 ?: 7;

                return $today->copy()->addDays($ahead)->toDateString();
            }
        }

        try {
            $date = Carbon::parse($datum)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return $date >= $today->toDateString() ? $date : null;
    }

    private function humanDate(string $date): string
    {
        $target = Carbon::parse($date);

        return match ($target->toDateString()) {
            now()->toDateString() => 'heute',
            now()->addDay()->toDateString() => 'morgen',
            default => 'am '.$this->weekdayLong($target).', '.$target->format('d.m.Y'),
        };
    }

    private function weekdayLong(Carbon $date): string
    {
        return ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][$date->dayOfWeekIso - 1];
    }

    /** @param Collection<int, Child> $children */
    private function whichChild(Collection $children): string
    {
        return 'Für welches Kind? Deine Kinder: '.$children->pluck('name')->implode(', ').'.';
    }

    private function help(): string
    {
        return "Hallo! 👋 Du kannst hier z. B. schreiben:\n• „Tom ist krank“\n• „Lena wird morgen um 16:30 abgeholt“\n• „Tom kommt beim Zoo-Ausflug mit“\n• „Wann geht Lena heute?“";
    }
}
