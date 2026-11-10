<?php

namespace App\Http\Controllers\Assistant;

use App\Assistant\AssistantAvailability;
use App\Assistant\ChatResponder;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\Assistant\SendMessageRequest;
use App\Models\Cart;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function store(
        SendMessageRequest $request,
        Conversation $conversation,
        ChatResponder $responder,
        AssistantAvailability $availability,
    ): JsonResponse {
        if (! $conversation->isActive()) {
            return response()->json(['error' => 'conversation_ended', 'message' => 'This chat has ended. Start a new one.'], 409);
        }

        if (! $availability->chatEnabled() || $availability->chatCapReached()) {
            return response()->json([
                'error' => 'assistant_unavailable',
                'message' => 'The assistant is not available right now. You can still browse and check out as usual.',
            ], 503);
        }

        $availability->countChatMessage();
        $result = $responder->respond($conversation, $request->validated('message'));

        $cart = Cart::open()->where('visitor_id', EnsureVisitorId::idFor($request))->latest('id')->first();

        return response()->json([
            'reply' => $result['reply'],
            'cart' => ['count' => $cart?->totalQuantity() ?? 0],
            'products' => $result['products'],
            ...($result['error'] ? ['error' => $result['error']] : []),
        ]);
    }
}
