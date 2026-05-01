<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveChatMessageRateLimitCharacter
{
    public function handle(Request $request, Closure $next): Response
    {
        $characterId = (int) $request->input('character_id', 0);

        if ($characterId > 0) {
            $request->attributes->set('rate_limited_character_id', $characterId);
        }

        return $next($request);
    }
}
