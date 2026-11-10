<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A conversation is only reachable by the visitor who started it, and only on its own channel
 * (`conversation.owner:text` or `conversation.owner:voice`). Anything else is a 404, so other
 * visitors cannot even learn that a UUID exists.
 */
class EnsureConversationOwner
{
    public function handle(Request $request, Closure $next, string $channel): Response
    {
        $conversation = $request->route('conversation');

        abort_unless(
            $conversation instanceof Conversation
                && $conversation->channel === $channel
                && hash_equals($conversation->visitor_id, EnsureVisitorId::idFor($request)),
            404,
        );

        return $next($request);
    }
}
