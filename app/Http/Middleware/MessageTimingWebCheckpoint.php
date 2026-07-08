<?php

namespace App\Http\Middleware;

use App\Support\MessageRequestTiming;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageTimingWebCheckpoint
{
    public function handle(Request $request, Closure $next): Response
    {
        MessageRequestTiming::checkpoint($request, 'after_web_group');

        return $next($request);
    }
}