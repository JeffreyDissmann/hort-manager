<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the Buchhaltung module by a user's accounting access. `accounting` (default)
 * requires read; `accounting:write` requires write. Independent of role/admin.
 */
class EnsureAccountingAccess
{
    public function handle(Request $request, Closure $next, string $level = 'read'): Response
    {
        $user = $request->user();

        $allowed = $level === 'write'
            ? (bool) $user?->canWriteAccounting()
            : (bool) $user?->canReadAccounting();

        abort_unless($allowed, 403);

        return $next($request);
    }
}
