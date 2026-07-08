<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MessageRequestTiming
{
    private const ATTRIBUTE = 'message_request_timing';

    public static function shouldTrack(Request $request): bool
    {
        if (! (bool) config('app.message_timing_log', false)) {
            return false;
        }

        return (string) ($request->route()?->getName() ?? '') === 'rooms.messages.store';
    }

    public static function start(Request $request): void
    {
        if (! self::shouldTrack($request) || self::enabled($request)) {
            return;
        }

        $request->attributes->set(self::ATTRIBUTE, [
            'enabled' => true,
            'route_name' => (string) ($request->route()?->getName() ?? ''),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_entry_at' => microtime(true),
            'query_count' => 0,
            'db_time_ms' => 0.0,
            'checkpoints' => [],
            'durations_ms' => [],
            'controller' => [],
        ]);
    }

    public static function enabled(Request $request): bool
    {
        return (bool) Arr::get(self::data($request), 'enabled', false);
    }

    public static function checkpoint(Request $request, string $name): ?float
    {
        if (! self::enabled($request)) {
            return null;
        }

        $now = microtime(true);
        self::set($request, 'checkpoints.' . $name, $now);

        return $now;
    }

    public static function set(Request $request, string $key, mixed $value): void
    {
        if (! self::enabled($request)) {
            return;
        }

        $data = self::data($request);
        Arr::set($data, $key, $value);
        $request->attributes->set(self::ATTRIBUTE, $data);
    }

    public static function get(Request $request, string $key, mixed $default = null): mixed
    {
        return Arr::get(self::data($request), $key, $default);
    }

    public static function recordDuration(Request $request, string $name, float $startedAt): ?float
    {
        if (! self::enabled($request)) {
            return null;
        }

        $durationMs = self::elapsedMs($startedAt, microtime(true));
        self::set($request, 'durations_ms.' . $name, $durationMs);

        return $durationMs;
    }

    public static function recordQuery(Request $request, QueryExecuted $query): void
    {
        if (! self::enabled($request)) {
            return;
        }

        $queryCount = (int) self::get($request, 'query_count', 0) + 1;
        $dbTimeMs = round((float) self::get($request, 'db_time_ms', 0.0) + (float) $query->time, 2);

        self::set($request, 'query_count', $queryCount);
        self::set($request, 'db_time_ms', $dbTimeMs);
    }

    public static function logSummary(Request $request, Response $response): void
    {
        if (! self::enabled($request)) {
            return;
        }

        $terminatedAt = microtime(true);
        self::set($request, 'terminated_at', $terminatedAt);

        $requestEntryAt = self::get($request, 'request_entry_at');
        $afterWebGroupAt = self::get($request, 'checkpoints.after_web_group');
        $notBannedEntryAt = self::get($request, 'checkpoints.not_banned_entry');
        $controllerEntryAt = self::get($request, 'checkpoints.controller_entry');
        $controllerReturnAt = self::get($request, 'checkpoints.controller_return');
        $beforeCharacterThrottleAt = self::get($request, 'checkpoints.before_chat_message_character_throttle');
        $afterCharacterThrottleAt = self::get($request, 'checkpoints.after_chat_message_character_throttle');
        $beforeUserThrottleAt = self::get($request, 'checkpoints.before_chat_message_user_throttle');
        $afterUserThrottleAt = self::get($request, 'checkpoints.after_chat_message_user_throttle');

        $summary = [
            'route_name' => self::get($request, 'route_name'),
            'method' => self::get($request, 'method'),
            'path' => self::get($request, 'path'),
            'status_code' => $response->getStatusCode(),
            'total_request_ms' => self::elapsedMs($requestEntryAt, $terminatedAt),
            'pre_controller_ms' => self::elapsedMs($requestEntryAt, $controllerEntryAt),
            'controller_ms' => self::get($request, 'controller.total_ms')
                ?? self::elapsedMs($controllerEntryAt, $controllerReturnAt),
            'after_controller_return_ms' => self::elapsedMs($controllerReturnAt, $terminatedAt),
            'web_middleware_ms' => self::elapsedMs($requestEntryAt, $afterWebGroupAt),
            'auth_not_banned_gap_ms' => self::elapsedMs($afterWebGroupAt, $notBannedEntryAt),
            'not_banned_ms' => self::get($request, 'durations_ms.not_banned'),
            'resolve_chat_message_character_ms' => self::get($request, 'durations_ms.resolve_chat_message_character'),
            'chat_message_character_throttle_ms' => self::elapsedMs($beforeCharacterThrottleAt, $afterCharacterThrottleAt),
            'chat_message_user_throttle_ms' => self::elapsedMs($beforeUserThrottleAt, $afterUserThrottleAt),
            'route_model_binding_controller_gap_ms' => self::elapsedMs($afterUserThrottleAt, $controllerEntryAt),
            'controller_sections_ms' => self::get($request, 'controller.sections_ms', []),
            'controller_validation_breakdown_ms' => self::get($request, 'controller.validation_breakdown_ms', []),
            'query_count' => self::get($request, 'query_count', 0),
            'db_time_ms' => self::get($request, 'db_time_ms', 0.0),
        ];

        Log::info('message request lifecycle timing', array_filter(
            $summary,
            static fn (mixed $value): bool => $value !== null && $value !== []
        ));
    }

    private static function data(Request $request): array
    {
        $data = $request->attributes->get(self::ATTRIBUTE, []);

        return is_array($data) ? $data : [];
    }

    private static function elapsedMs(?float $start, ?float $end): ?float
    {
        if ($start === null || $end === null) {
            return null;
        }

        return round(($end - $start) * 1000, 2);
    }
}