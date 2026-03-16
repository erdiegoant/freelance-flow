<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCallbackFromWorker
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->secretMatches($request) || ! $this->ipIsAllowed($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }

    private function secretMatches(Request $request): bool
    {
        $secret = config('services.invoice_worker.callback_secret');

        return $request->header('X-Callback-Secret') === $secret;
    }

    private function ipIsAllowed(Request $request): bool
    {
        $host = config('services.invoice_worker.go_worker_host');
        $resolvedIp = gethostbyname($host);

        return $request->ip() === $resolvedIp;
    }
}
