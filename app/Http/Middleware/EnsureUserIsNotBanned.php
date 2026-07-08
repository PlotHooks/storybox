<?php

namespace App\Http\Middleware;

use App\Support\MessageRequestTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = MessageRequestTiming::checkpoint($request, 'not_banned_entry') ?? microtime(true);
        $user = $request->user();

        if (! $user || ! $user->is_banned) {
            MessageRequestTiming::recordDuration($request, 'not_banned', $startedAt);

            return $next($request);
        }

        if ($user->banned_until && $user->banned_until->isPast()) {
            $user->forceFill([
                'is_banned' => false,
                'banned_until' => null,
                'banned_reason' => null,
            ])->save();

            MessageRequestTiming::recordDuration($request, 'not_banned', $startedAt);

            return $next($request);
        }

        MessageRequestTiming::recordDuration($request, 'not_banned', $startedAt);

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
