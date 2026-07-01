<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
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
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // Public VAPID key so the browser can subscribe to web push.
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            // The latest "Was ist neu?" entries (newest first, max 5); the popup
            // auto-shows the newest if unseen and lets users page back through them.
            'whatsNew' => array_slice((array) config('whats_new'), 0, 5),
            // Open excursion polls still awaiting an answer for this parent's children.
            'pendingPolls' => fn () => $this->pendingPollsCount($request->user()),
        ];
    }

    /** How many (child, excursion) poll answers this parent still owes. */
    private function pendingPollsCount(?User $user): int
    {
        if (! $user || $user->isStaff()) {
            return 0;
        }

        // Pivot constraints inside Eloquent count-subqueries are unreliable for the
        // pivot-less Child::excursions relation, so count the join directly.
        return DB::table('child_excursion')
            ->join('excursions', 'excursions.id', '=', 'child_excursion.excursion_id')
            ->join('child_user', 'child_user.child_id', '=', 'child_excursion.child_id')
            ->where('child_user.user_id', $user->id)
            ->whereNull('child_excursion.response')
            ->where(fn ($q) => $q->whereNull('excursions.rsvp_deadline')
                ->orWhereDate('excursions.rsvp_deadline', '>=', now()->toDateString()))
            ->count();
    }
}
