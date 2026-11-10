<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When ASSISTANT_ACCESS_CODE is set, chat and voice stay closed until the visitor has entered the code once
 * in this session (POST /assistant/access). The storefront itself is never gated.
 */
class EnsureAssistantAccess
{
    public const SESSION_KEY = 'assistant_access';

    public function handle(Request $request, Closure $next): Response
    {
        if (filled(config('assistant.access_code')) && ! $request->session()->get(self::SESSION_KEY)) {
            return response()->json([
                'error' => 'access_code_required',
                'message' => 'Enter the access code to use the assistant.',
            ], 403);
        }

        return $next($request);
    }
}
