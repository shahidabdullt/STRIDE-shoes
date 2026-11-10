<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('channel', ['text', 'voice']);
            $table->enum('voice_mode', ['hands_free', 'hold_to_talk'])->nullable();
            $table->enum('device_class', ['mobile', 'desktop']);
            $table->char('visitor_id', 36)->index();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_config_version_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'ended', 'failed'])->default('active');
            $table->enum('ended_reason', [
                'user_ended', 'idle', 'go_away_failed', 'max_duration', 'token_expired', 'mic_denied', 'backgrounded', 'error',
            ])->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->enum('outcome', ['pending', 'success', 'failed'])->default('pending');
            $table->enum('failure_kind', ['knowledge_gap', 'technical', 'both'])->nullable();
            $table->unsignedSmallInteger('questions_total')->default(0);
            $table->unsignedSmallInteger('questions_unanswered')->default(0);
            $table->foreignId('outcome_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('analysis_status', ['pending', 'running', 'done', 'failed', 'skipped'])->default('pending');
            $table->text('analysis_error')->nullable();
            $table->timestamps();

            $table->index(['outcome', 'started_at']);
            $table->index(['status', 'last_activity_at']);
            $table->index(['channel', 'started_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->text('content');
            $table->string('tool_name', 64)->nullable();
            $table->json('tool_args')->nullable();
            $table->json('tool_result')->nullable();
            $table->enum('source', ['server', 'client'])->default('server');
            $table->enum('status', ['ok', 'error'])->default('ok');
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 500);
            $table->string('topic', 60);
            $table->enum('outcome', ['answered', 'not_answered', 'out_of_scope']);
            $table->enum('source', ['catalog', 'knowledge', 'both', 'none']);
            $table->string('reason', 300);
            $table->enum('detected_by', ['classifier', 'model_report']);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['outcome', 'resolved_at', 'created_at']);
            $table->index('topic');
        });

        // Phase 2 left orders.conversation_id without a key because the table did not exist yet.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });
        Schema::dropIfExists('conversation_questions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
