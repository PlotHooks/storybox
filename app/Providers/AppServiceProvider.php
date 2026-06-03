<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme('https');

        Gate::define('accessFilament', function ($user): bool {
            $allowed = (bool) $user->is_admin;

            if (! $allowed && $this->shouldLogAdminAccessAttempt()) {
                Log::warning('Suspicious admin access attempt', [
                    'user_id' => $user->id,
                    'route' => request()->route()?->getName(),
                    'reason' => 'non_admin_filament_access',
                ]);
            }

            return $allowed;
        });

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('chat-message-character', function (Request $request) {
            return $this->secondsLimit(
                (int) config('rate_limits.chat_message_character_max', 5),
                (int) config('rate_limits.chat_message_character_decay', 10),
                $this->characterRateLimitKey($request)
            );
        });

        RateLimiter::for('chat-message-user', function (Request $request) {
            return $this->secondsLimit(
                (int) config('rate_limits.chat_message_user_max', 20),
                (int) config('rate_limits.chat_message_user_decay', 60),
                $this->userRateLimitKey($request)
            );
        });

        RateLimiter::for('message-report', function (Request $request) {
            return $this->secondsLimit(
                (int) config('rate_limits.message_report_max', 5),
                (int) config('rate_limits.message_report_decay', 60),
                $this->userRateLimitKey($request)
            );
        });

        RateLimiter::for('dm-action', function (Request $request) {
            return $this->secondsLimit(
                (int) config('rate_limits.dm_action_max', 30),
                (int) config('rate_limits.dm_action_decay', 60),
                $this->userRateLimitKey($request)
            );
        });

        RateLimiter::for('profile-update', function (Request $request) {
            return $this->secondsLimit(
                (int) config('rate_limits.profile_update_max', 10),
                (int) config('rate_limits.profile_update_decay', 60),
                $this->userRateLimitKey($request)
            );
        });
    }

    private function secondsLimit(int $maxAttempts, int $decaySeconds, string $key): Limit
    {
        return (new Limit(key: '', maxAttempts: $maxAttempts, decaySeconds: $decaySeconds))
            ->by($key)
            ->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Slow down and try again in a moment.',
                    'retry_after' => isset($headers['Retry-After'])
                        ? (int) $headers['Retry-After']
                        : null,
                ], 429, $headers);
            });
    }

    private function userRateLimitKey(Request $request): string
    {
        return 'user:' . ($request->user()?->id ?: $request->ip());
    }

    private function characterRateLimitKey(Request $request): string
    {
        $userKey = $this->userRateLimitKey($request);
        $characterId = (int) $request->attributes->get('rate_limited_character_id', 0);

        return $characterId > 0
            ? 'character:' . $characterId
            : $userKey . ':no-character';
    }

    private function shouldLogAdminAccessAttempt(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');
        $path = trim((string) request()->path(), '/');

        if (str_starts_with($path, 'admin')) {
            return true;
        }

        return str_contains($routeName, 'filament')
            || $routeName === 'filament.admin.pages.live-moderation'
            || $routeName === 'broadcasting.auth';
    }
}
