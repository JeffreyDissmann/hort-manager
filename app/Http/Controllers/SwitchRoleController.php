<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-only: switch your own role between Erzieher (staff) and Elternteil (parent)
 * straight from the menu — a real, persisted change (admin status is untouched).
 */
class SwitchRoleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user->role = UserRole::from($validated['role']);
        $user->save();

        // Stay on the page the switch was made from (most pages work for both roles).
        return back();
    }
}
