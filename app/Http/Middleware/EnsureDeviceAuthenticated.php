<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authenticated user via Sanctum or Session
        if ($request->user() !== null) {
            return $next($request);
        }

        // 2. Device Token authentication via X-Device-Token, X-API-Key header or device_token query
        $deviceToken = $request->header('X-Device-Token') ?? $request->header('X-API-Key') ?? $request->query('device_token');
        $configuredToken = config('app.offline_device_token') ?: env('OFFLINE_DEVICE_TOKEN');

        if (!empty($configuredToken) && !empty($deviceToken) && hash_equals((string) $configuredToken, (string) $deviceToken)) {
            return $next($request);
        }

        // 3. Request Signature authentication (X-Signature, X-Timestamp)
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $secret = config('app.offline_device_secret') ?: env('OFFLINE_DEVICE_SECRET');

        if (!empty($secret) && !empty($signature) && !empty($timestamp)) {
            if (abs(time() - (int) $timestamp) <= 300) {
                $payload = $request->getMethod() . '|' . $request->fullUrl() . '|' . $timestamp . '|' . $request->getContent();
                $expectedSignature = hash_hmac('sha256', $payload, $secret);
                if (hash_equals($expectedSignature, $signature)) {
                    return $next($request);
                }
            }
        }

        abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated or invalid device authentication credentials.');
    }
}
