<?php

namespace App\Http\Middleware;

use App\Jobs\LogRequest as JobsLogRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if ($path == 'api/read-request-logs') {
            return $next($request);
        }
        $startTime = now();
        $response = $next($request);
        $requestData = [
            'route' => $path,
            'request_ipv4' => $request->ip(),
            'request_ipv6' => NULL,
            'method' => $request->method(),
            'payload' => $path == 'operation/imagesAdd' ? Arr::except($request->all(), 'images') : $request->all(),
            'headers' => $request->server(),
        ];
        $responseData = [
            'successful' => (int)!($response->isServerError() || $response->isClientError()),
            'status_code' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
        ];
        JobsLogRequest::dispatch(
            $startTime,
            $requestData,
            $responseData,
            now()->diffInSeconds($startTime),
            memory_get_peak_usage(true)
        );
        return $response;
    }
}
