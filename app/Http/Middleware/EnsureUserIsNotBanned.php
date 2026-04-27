<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_banned) {
            return $next($request);
        }

        if ($user->banned_until && $user->banned_until->isPast()) {
            $user->forceFill([
                'is_banned' => false,
                'banned_until' => null,
                'banned_reason' => null,
            ])->save();

            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, 'This account has been banned.');
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'This account has been banned.']);
    }
}
