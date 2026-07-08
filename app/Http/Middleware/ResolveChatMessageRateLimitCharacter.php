<?php

namespace App\Http\Middleware;

use App\Models\Character;
use App\Support\MessageRequestTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveChatMessageRateLimitCharacter
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $characterId = (int) $request->input('character_id', 0);

        $ownedCharacter = $characterId > 0 && $request->user()
            ? Character::query()
                ->where('id', $characterId)
                ->where('user_id', $request->user()->id)
                ->first()
            : null;

        if ($ownedCharacter !== null) {
            $request->attributes->set('rate_limited_character_id', $characterId);
            $request->attributes->set('resolved_owned_character', $ownedCharacter);
        } elseif ($characterId > 0 && $request->user()) {
            Log::warning('Suspicious chat rate limit character mismatch', [
                'user_id' => $request->user()->id,
                'submitted_character_id' => $characterId,
                'route' => $request->route()?->getName(),
                'reason' => 'rate_limit_character_not_owned',
            ]);
        }

        MessageRequestTiming::recordDuration($request, 'resolve_chat_message_character', $startedAt);

        return $next($request);
    }
}
