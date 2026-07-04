<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /** Save the signed-in user's preferred UI language. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales')))],
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        return back()->with('status', __('profile.language_saved'));
    }
}
