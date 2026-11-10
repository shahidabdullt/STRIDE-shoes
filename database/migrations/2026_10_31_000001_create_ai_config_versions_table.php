<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_config_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->text('system_prompt');
            $table->text('voice_style_prompt')->nullable();
            $table->string('text_model', 100);
            $table->string('live_model', 100);
            $table->string('live_voice', 50)->nullable();
            $table->decimal('temperature', 3, 2);
            $table->unsignedInteger('max_output_tokens');
            $table->unsignedSmallInteger('vad_silence_ms');
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('voice_enabled')->default(true);
            $table->unsignedSmallInteger('voice_max_seconds');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_config_versions');
    }
};
