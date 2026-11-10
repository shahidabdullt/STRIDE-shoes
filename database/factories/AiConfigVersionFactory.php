<?php

namespace Database\Factories;

use App\Models\AiConfigVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiConfigVersion> */
class AiConfigVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => AiConfigVersion::nextVersionNumber(),
            'system_prompt' => 'You are the Stride shoe store assistant.',
            'voice_style_prompt' => null,
            'text_model' => 'gemini-3.6-flash',
            'live_model' => 'gemini-3.8-live',
            'live_voice' => null,
            'temperature' => 0.4,
            'max_output_tokens' => 1024,
            'vad_silence_ms' => 800,
            'chat_enabled' => true,
            'voice_enabled' => true,
            'voice_max_seconds' => 600,
            'is_active' => false,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
