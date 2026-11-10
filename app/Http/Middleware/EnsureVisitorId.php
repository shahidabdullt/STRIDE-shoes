<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every browser a random, HttpOnly visitor id (no accounts for shoppers).
 * The id is exposed to the rest of the request as the `visitor_id` attribute.
 */
class EnsureVisitorId
{
    public const COOKIE = 'visitor_id';

    private const LIFETIME_MINUTES = 60 * 24 * 30;

    /** The visitor id set by this middleware for the current request. */
    public static function idFor(Request $request): string
    {
        return $request->attributes->get(self::COOKIE);
    }

    /**
     * A rate-limit key. Laravel runs throttling before this middleware, so the attribute may not be set yet:
     * fall back to the (already decrypted) cookie, then to the IP for a browser that has no cookie yet.
     */
    public static function rateLimitKey(Request $request): string
    {
        $id = $request->attributes->get(self::COOKIE) ?? $request->cookie(self::COOKIE);

        return is_string($id) && Str::isUuid($id) ? 'v:'.$id : 'ip:'.$request->ip();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie(self::COOKIE);

        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();

            Cookie::queue(Cookie::make(
                name: self::COOKIE,
                value: $visitorId,
                minutes: self::LIFETIME_MINUTES,
                secure: config('session.secure'),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        $request->attributes->set(self::COOKIE, $visitorId);

        return $next($request);
    }
}
