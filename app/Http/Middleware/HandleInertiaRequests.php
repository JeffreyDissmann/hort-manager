<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Support\PickupClashes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            // Only what the UI needs — not the raw model (keeps slack_id off the client).
            'auth' => [
                'user' => fn () => ($user = $request->user()) ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role->value,
                    'is_admin' => $user->is_admin,
                    // Accounting access axis (independent of role/admin) — drives nav
                    // visibility and hiding write controls in the accounting UI.
                    'can_read_accounting' => $user->canReadAccounting(),
                    'can_write_accounting' => $user->canWriteAccounting(),
                    'email_verified_at' => $user->email_verified_at,
                    'locale' => $user->locale,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Active UI locale, the languages a user can pick, and the full message
            // catalog for the active locale — for the frontend $t() helper.
            'locale' => app()->getLocale(),
            'locales' => config('locales'),
            'translations' => fn () => $this->translations(app()->getLocale()),
            // Public VAPID key so the browser can subscribe to web push.
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            // The latest "Was ist neu?" entries (newest first, max 5); the popup
            // auto-shows the newest if unseen and lets users page back through them.
            'whatsNew' => array_slice((array) config('whats_new'), 0, 5),
            // Open excursion polls still awaiting an answer for this parent's children.
            'pendingPolls' => fn () => $this->pendingPollsCount($request->user()),
            // „Geht mit … mit" arrangements still awaiting this parent's confirmation
            // (their child is the companion another child wants to go home with).
            'pendingCompanions' => fn () => $this->pendingCompanionCount($request->user()),
            // This parent's children whose Stammplan isn't set up yet (drives a banner).
            'childrenWithoutPlan' => fn () => $this->childrenWithoutPlan($request->user()),
            // Pickups of this parent's children that land in the Hausaufgabenzeit, a
            // timed Aktivität or an Ausflug — the standing summary above the page.
            'pickupClashes' => fn () => PickupClashes::for($request->user()),
            // Ferienbetreuungen still open whose sign-up this parent hasn't answered.
            'pendingCare' => fn () => $this->pendingCare($request->user()),
            // Hort-wide cutoff (H:i) after which same-day changes notify staff — the
            // DayEditor warns parents about it before they save.
            'lateChangeCutoff' => fn () => $request->user() ? Setting::lateChangeCutoff() : null,
        ];
    }

    /**
     * Ferienbetreuungen whose registration is still open and for which at least one of
     * this parent's children hasn't answered — „keine Tage" counts as an answer, so a
     * family that consciously opted out is left alone. Staff sign anyone up any time,
     * so they get no nudge.
     *
     * @return list<array{id: int, name: string, deadline: ?string, children: list<string>}>
     */
    private function pendingCare(?User $user): array
    {
        if (! $user || $user->isStaff()) {
            return [];
        }

        $childIds = $user->children()->pluck('children.id');

        if ($childIds->isEmpty()) {
            return [];
        }

        $periods = HolidayPeriod::query()
            ->care()
            ->whereDate('ends_on', '>=', Carbon::today())
            ->orderBy('starts_on')
            ->get()
            ->filter(fn (HolidayPeriod $period): bool => $period->registrationIsOpen());

        if ($periods->isEmpty()) {
            return [];
        }

        // Two queries for all periods instead of two per period: this runs on every
        // request, and a family with several children and a couple of open
        // Ferienbetreuungen paid for each of them separately.
        $answers = HolidayCareAnswer::query()
            ->whereIn('holiday_period_id', $periods->modelKeys())
            ->whereIn('child_id', $childIds)
            ->get(['holiday_period_id', 'child_id'])
            ->groupBy('holiday_period_id');

        $children = Child::query()->whereIn('id', $childIds)->orderBy('name')->get();

        return $periods
            ->map(function (HolidayPeriod $period) use ($answers, $children): ?array {
                $answered = $answers->get($period->id, collect())->pluck('child_id');

                // Enrolment overlap in memory — the same inclusive test as activeBetween.
                $missing = $children
                    ->reject(fn (Child $child): bool => $answered->contains($child->id))
                    ->filter(fn (Child $child): bool => ($child->active_from === null || $child->active_from->lte($period->ends_on))
                        && ($child->active_until === null || $child->active_until->gte($period->starts_on)))
                    ->pluck('name');

                return $missing->isEmpty() ? null : [
                    'id' => $period->id,
                    'name' => $period->name,
                    'deadline' => $period->registration_deadline?->toDateString(),
                    'children' => $missing->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * This parent's own children that still have no Stammplan — nudged with a banner
     * to set it. Staff manage every child, so they get no such reminder.
     *
     * @return list<array{id: int, name: string}>
     */
    private function childrenWithoutPlan(?User $user): array
    {
        if (! $user || $user->isStaff()) {
            return [];
        }

        return $user->children()
            ->withoutSchedule()
            ->activeOn(now())
            ->orderBy('name')
            ->get(['children.id', 'name'])
            ->map(fn (Child $child) => ['id' => $child->id, 'name' => $child->name])
            ->all();
    }

    /**
     * The UI message catalog for a locale, keyed by namespace (file name). German
     * is the base so any untranslated key falls back to it. Framework message
     * files (auth/passwords/validation) stay server-side.
     *
     * @return array<string, mixed>
     */
    private function translations(string $locale): array
    {
        $load = function (string $loc): array {
            $out = [];
            foreach (glob(lang_path($loc).'/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                if (in_array($name, ['auth', 'passwords', 'validation'], true)) {
                    continue;
                }
                $out[$name] = require $file;
            }

            return $out;
        };

        $base = $load('de');

        return $locale === 'de' ? $base : array_replace_recursive($base, $load($locale));
    }

    /** How many (child, excursion) poll answers this parent still owes. */
    private function pendingPollsCount(?User $user): int
    {
        if (! $user || $user->isStaff()) {
            return 0;
        }

        // Pivot constraints inside Eloquent count-subqueries are unreliable for the
        // pivot-less Child::excursions relation, so count the join directly.
        //
        // „Still owes" runs until the trip, not until the Anmeldeschluss: a family that
        // never answered may answer right up to the trip day (Excursion::parentMayAnswer)
        // and is reminded daily, so the badge and the banner have to stay up as well.
        return DB::table('child_excursion')
            ->join('excursions', 'excursions.id', '=', 'child_excursion.excursion_id')
            ->join('child_user', 'child_user.child_id', '=', 'child_excursion.child_id')
            ->where('child_user.user_id', $user->id)
            ->whereNull('child_excursion.response')
            ->whereDate('excursions.date', '>=', now()->toDateString())
            ->count();
    }

    /**
     * How many „geht mit … mit" arrangements still await this parent's confirmation —
     * one of their children is the companion, and the answer is still open (from today
     * on). Staff get 0: they aren't asked personally (they see every request on the plan).
     */
    private function pendingCompanionCount(?User $user): int
    {
        if (! $user || $user->isStaff()) {
            return 0;
        }

        return DailyDeparture::query()
            ->pendingCompanion()
            ->where('date', '>=', now()->toDateString())
            ->whereIn('companion_child_id', $user->children()->pluck('children.id'))
            ->count();
    }
}
