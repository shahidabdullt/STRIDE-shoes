<?php

namespace App\Providers;

use App\Actions\Cart\ResolveCart;
use App\Assistant\AssistantAvailability;
use App\Http\Middleware\EnsureVisitorId;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The header's cart badge. Uses the visitor id set by EnsureVisitorId; a read only, no cart is created.
        View::composer('components.layouts.app', function ($view): void {
            $request = request();
            $visitorId = $request->attributes->get(EnsureVisitorId::COOKIE);
            $cart = $visitorId ? app(ResolveCart::class)($visitorId) : null;

            $view->with('cartCount', $cart?->totalQuantity() ?? 0);
        });

        // The chat widget renders in an "unavailable" state when the admin has switched chat off.
        View::composer('components.chat-widget', function ($view): void {
            $view->with('chatEnabled', app(AssistantAvailability::class)->chatEnabled());
        });

        View::composer('components.voice-panel', function ($view): void {
            $view->with('voiceEnabled', app(AssistantAvailability::class)->voiceEnabled());
        });

        Gate::define('access-admin', fn (User $user): bool => (bool) $user->is_admin);

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        // Assistant limits (BLUEPRINT §5.1), keyed by the visitor cookie. IPs are only used inside the cache.
        $tooMany = fn (string $message) => fn () => response()->json(['error' => 'rate_limited', 'message' => $message], 429);

        RateLimiter::for('assistant-chat', fn (Request $request): array => [
            Limit::perMinute(20)->by('chat-min:'.EnsureVisitorId::rateLimitKey($request))
                ->response($tooMany('You are sending messages too quickly. Please wait a minute and try again.')),
            Limit::perDay(200)->by('chat-day:'.EnsureVisitorId::rateLimitKey($request))
                ->response($tooMany("You have reached today's chat limit. Please come back tomorrow.")),
        ]);

        RateLimiter::for('assistant-start', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('chat-start:'.EnsureVisitorId::rateLimitKey($request))
            ->response($tooMany('Too many new chats. Please wait a minute.')));

        RateLimiter::for('assistant-access', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('chat-access:'.EnsureVisitorId::rateLimitKey($request).'|'.$request->ip())
            ->response($tooMany('Too many attempts. Please wait a minute.')));

        // Voice: 5 tokens per 10 minutes per visitor+IP; per-conversation limits for tool calls and transcripts.
        // Throttling runs before route-model binding, so the route parameter may still be the raw UUID.
        $conversationKey = fn (Request $request): string => (string) ($request->route('conversation')?->uuid ?? $request->route('conversation'));

        RateLimiter::for('voice-token', fn (Request $request): Limit => Limit::perMinutes(10, 5)
            ->by('voice-token:'.EnsureVisitorId::rateLimitKey($request).'|'.$request->ip())
            ->response($tooMany('Too many voice sessions. Please wait a few minutes.')));

        RateLimiter::for('voice-tools', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('voice-tools:'.$conversationKey($request))
            ->response($tooMany('Too many tool calls.')));

        RateLimiter::for('voice-transcripts', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('voice-transcripts:'.$conversationKey($request))
            ->response($tooMany('Too many transcript updates.')));
    }
}
