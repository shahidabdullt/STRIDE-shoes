<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_point_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label');
            $table->text('instruction');
            $table->enum('type', ['string', 'number', 'boolean', 'enum']);
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('conversation_data_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_point_definition_id')->constrained()->cascadeOnDelete();
            $table->string('value_text', 500)->nullable();
            $table->decimal('value_number', 12, 2)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->string('evidence', 300)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['conversation_id', 'data_point_definition_id'], 'conversation_data_point_unique');
            $table->index(['data_point_definition_id', 'value_number'], 'conversation_data_points_definition_number_index');
            $table->rawIndex('data_point_definition_id, value_text(64)', 'conversation_data_points_definition_text_index');
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('rubric');
            $table->enum('scale', ['pass_fail', 'score_1_5']);
            $table->enum('applies_to', ['all', 'text', 'voice'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('conversation_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->cascadeOnDelete();
            $table->boolean('passed')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('rationale');
            $table->string('evaluator_model', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['conversation_id', 'evaluation_criterion_id'], 'conversation_evaluation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_evaluations');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('conversation_data_points');
        Schema::dropIfExists('data_point_definitions');
    }
};
