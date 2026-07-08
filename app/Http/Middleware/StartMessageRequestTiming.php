<?php

namespace App\Http\Middleware;

use App\Support\MessageRequestTiming;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StartMessageRequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        MessageRequestTiming::start($request);

        if (MessageRequestTiming::enabled($request)) {
            DB::listen(function (QueryExecuted $query) use ($request): void {
                MessageRequestTiming::recordQuery($request, $query);
            });
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        MessageRequestTiming::logSummary($request, $response);
    }
}