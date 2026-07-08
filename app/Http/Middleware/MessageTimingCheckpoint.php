<?php

namespace App\Http\Middleware;

use App\Support\MessageRequestTiming;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageTimingCheckpoint
{
    public function handle(Request $request, Closure $next, string $name): Response
    {
        MessageRequestTiming::checkpoint($request, $name);

        return $next($request);
    }
}