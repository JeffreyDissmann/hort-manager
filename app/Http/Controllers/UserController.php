<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SlackUserImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /** Admin-only: list every user with their role + admin status. */
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Users/Index', [
            'users' => User::orderBy('name')
                ->get(['id', 'name', 'email', 'avatar', 'role', 'is_admin'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role->value,
                    'is_admin' => $user->is_admin,
                    'is_self' => $user->is($request->user()),
                ]),
            'roleOptions' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    /** Admin-only: change a user's role and/or admin status. */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_admin' => ['required', 'boolean'],
        ]);

        $role = UserRole::from($validated['role']);
        // Role (teacher access) and admin (user management) are independent.
        $isAdmin = $validated['is_admin'];

        // Never leave the Hort without an admin.
        if ($user->is_admin && ! $isAdmin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('status', 'Es muss mindestens eine:n Administrator:in geben.');
        }

        $user->forceFill(['role' => $role, 'is_admin' => $isAdmin])->save();

        return back()->with('status', "{$user->name} aktualisiert.");
    }

    /** Admin-only: delete a user (guardian links cascade; board/poll references null out). */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($user->is($request->user())) {
            return back()->with('status', 'Du kannst dich nicht selbst löschen.');
        }

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('status', 'Es muss mindestens eine:n Administrator:in geben.');
        }

        $user->delete();

        return back()->with('status', "{$user->name} wurde gelöscht.");
    }

    /** Admin-only: import/refresh all Slack workspace members. */
    public function sync(Request $request, SlackUserImporter $importer): RedirectResponse
    {
        $this->ensureAdmin($request);

        $count = $importer->run();

        return back()->with('status', "{$count} Benutzer aus Slack synchronisiert.");
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
