<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\MessageTimingCheckpoint;
use App\Http\Middleware\MessageTimingWebCheckpoint;
use App\Http\Middleware\ResolveChatMessageRateLimitCharacter;
use App\Http\Middleware\StartMessageRequestTiming;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->trimStrings(except: [
            'delete_confirmation',
        ]);
        $middleware->web(prepend: [StartMessageRequestTiming::class], append: [MessageTimingWebCheckpoint::class]);

        $middleware->alias([
            'not_banned' => EnsureUserIsNotBanned::class,
            'resolve_chat_message_character' => ResolveChatMessageRateLimitCharacter::class,
            'message_timing_checkpoint' => MessageTimingCheckpoint::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            $routeName = $request->route()?->getName();
            $roomManagementRoutes = [
                'rooms.update',
                'rooms.whitelist.store',
                'rooms.whitelist.destroy',
                'rooms.blacklist.store',
                'rooms.blacklist.destroy',
                'rooms.account-blacklist.store',
                'rooms.account-blacklist.destroy',
                'rooms.kick.store',
                'rooms.moderators.store',
                'rooms.moderators.destroy',
            ];

            if (! $request->expectsJson() || ! in_array($routeName, $roomManagementRoutes, true)) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'SESSION_EXPIRED',
                    'message' => 'Your session expired. Refresh the room and try again.',
                ],
            ], 419);
        });
    })
    ->create();
