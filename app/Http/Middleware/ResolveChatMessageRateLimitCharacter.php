<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveChatMessageRateLimitCharacter
{
    public function handle(Request $request, Closure $next): Response
    {
        $characterId = (int) $request->input('character_id', 0);

        $ownedCharacter = $characterId > 0 && $request->user() && DB::table('characters')
            ->where('id', $characterId)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($ownedCharacter) {
            $request->attributes->set('rate_limited_character_id', $characterId);
        } elseif ($characterId > 0 && $request->user()) {
            Log::warning('Suspicious chat rate limit character mismatch', [
                'user_id' => $request->user()->id,
                'submitted_character_id' => $characterId,
                'route' => $request->route()?->getName(),
                'reason' => 'rate_limit_character_not_owned',
            ]);
        }

        return $next($request);
    }
}
