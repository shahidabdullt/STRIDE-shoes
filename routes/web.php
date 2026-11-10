<?php

use App\Http\Controllers\Admin\AiConfigController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ConversationController as AdminConversationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataPointDefinitionController;
use App\Http\Controllers\Admin\EvaluationCriterionController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\KnowledgeArticleController;
use App\Http\Controllers\Admin\KnowledgeGapController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Assistant\AccessController;
use App\Http\Controllers\Assistant\ConversationController;
use App\Http\Controllers\Assistant\MessageController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CartItemController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\FaqController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderConfirmationController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Voice\SessionController as VoiceSessionController;
use App\Http\Controllers\Voice\ToolCallController;
use App\Http\Controllers\Voice\TranscriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/shoes', [ProductController::class, 'index'])->name('products.index');
Route::get('/shoes/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/help', FaqController::class)->name('faqs.index');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartItemController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/orders/{order:number}/confirmation', OrderConfirmationController::class)
    ->middleware('signed')->name('orders.confirmation');

// Text assistant (JSON, first-party AJAX: session + CSRF from the web group).
Route::prefix('assistant')->name('assistant.')->group(function () {
    Route::post('access', [AccessController::class, 'store'])->middleware('throttle:assistant-access')->name('access');

    Route::middleware('assistant.access')->group(function () {
        Route::post('conversations', [ConversationController::class, 'store'])
            ->middleware('throttle:assistant-start')->name('conversations.store');

        Route::middleware('conversation.owner:text')->prefix('conversations/{conversation:uuid}')->group(function () {
            Route::get('/', [ConversationController::class, 'show'])->name('conversations.show');
            Route::post('messages', [MessageController::class, 'store'])
                ->middleware('throttle:assistant-chat')->name('messages.store');
            Route::post('end', [ConversationController::class, 'end'])->name('conversations.end');
        });
    });
});

// Voice assistant (JSON). Audio goes browser <-> Gemini Live directly; these endpoints mint the token,
// run forwarded tool calls and store transcripts. The same access gate as the text chat applies.
Route::prefix('voice')->name('voice.')->middleware('assistant.access')->group(function () {
    Route::post('sessions', [VoiceSessionController::class, 'store'])
        ->middleware('throttle:voice-token')->name('sessions.store');

    Route::middleware('conversation.owner:voice')->prefix('sessions/{conversation:uuid}')->group(function () {
        Route::post('token', [VoiceSessionController::class, 'token'])->middleware('throttle:voice-token')->name('sessions.token');
        Route::post('tool-calls', [ToolCallController::class, 'store'])->middleware('throttle:voice-tools')->name('tool-calls.store');
        Route::post('transcripts', [TranscriptController::class, 'store'])->middleware('throttle:voice-transcripts')->name('transcripts.store');
        Route::post('end', [VoiceSessionController::class, 'end'])->name('sessions.end');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:admin-login');
    });

    Route::middleware(['auth', 'can:access-admin'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('products', AdminProductController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('faqs', AdminFaqController::class)->except('show');
        Route::resource('knowledge', KnowledgeArticleController::class)->except('show')->parameters(['knowledge' => 'article']);
        Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);

        Route::get('conversations', [AdminConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{conversation}', [AdminConversationController::class, 'show'])->name('conversations.show');
        Route::post('conversations/{conversation}/analyze', [AdminConversationController::class, 'analyze'])->name('conversations.analyze');
        Route::patch('conversations/{conversation}/outcome', [AdminConversationController::class, 'outcome'])->name('conversations.outcome');
        Route::delete('conversations/{conversation}', [AdminConversationController::class, 'destroy'])->name('conversations.destroy');

        Route::get('knowledge-gaps', [KnowledgeGapController::class, 'index'])->name('knowledge-gaps.index');
        Route::get('knowledge-gaps/{question}/faq', [KnowledgeGapController::class, 'faq'])->name('knowledge-gaps.faq');
        Route::post('knowledge-gaps/{question}/resolve', [KnowledgeGapController::class, 'resolve'])->name('knowledge-gaps.resolve');

        Route::resource('data-points', DataPointDefinitionController::class)->except('show');
        Route::resource('evaluation-criteria', EvaluationCriterionController::class)->except('show');

        Route::get('ai-config', [AiConfigController::class, 'index'])->name('ai-config.index');
        Route::post('ai-config', [AiConfigController::class, 'store'])->name('ai-config.store');
        Route::post('ai-config/{version}/activate', [AiConfigController::class, 'activate'])->name('ai-config.activate');
    });
});
