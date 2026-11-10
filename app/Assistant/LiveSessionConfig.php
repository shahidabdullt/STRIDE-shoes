<?php

namespace App\Assistant;

use App\Models\AiConfigVersion;

/**
 * Builds the Live API setup that is locked into the ephemeral token (BLUEPRINT §3.2, Phase 0 S1).
 *
 * Every field is locked through the token's fieldMask except `sessionResumption`, which the browser
 * supplies so it can resume with a handle. Verified live on 8 Nov 2026: with this token a browser-sent
 * system instruction is ignored, tool calls work, resuming on the same token keeps context, and a second
 * new session on the token is refused.
 *
 * Never sent (both are errors or unsupported on gemini-3.8-live): thinkingConfig, proactivity.proactiveAudio=false.
 */
class LiveSessionConfig
{
    public function __construct(
        private PromptBuilder $prompts,
        private ToolRegistry $registry,
    ) {}

    /** @return array<string, mixed> Raw camelCase BidiGenerateContentSetup JSON. */
    public function setup(AiConfigVersion $config, string $mode): array
    {
        $generation = [
            'responseModalities' => ['AUDIO'],
            'temperature' => (float) $config->temperature,
        ];

        if (filled($config->live_voice)) {
            $generation['speechConfig'] = ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $config->live_voice]]];
        }

        // Hold-to-talk: the client marks speech with activityStart/activityEnd, so the mic is never
        // streamed while the model speaks and speaker echo cannot interrupt it.
        $activity = $mode === 'hold_to_talk'
            ? ['disabled' => true]
            : ['disabled' => false, 'silenceDurationMs' => (int) $config->vad_silence_ms];

        return [
            'model' => $this->modelName($config),
            'generationConfig' => $generation,
            'systemInstruction' => ['parts' => [['text' => $this->prompts->build($config, 'voice')]]],
            'tools' => [['functionDeclarations' => $this->registry->liveDeclarations()]],
            'realtimeInputConfig' => ['automaticActivityDetection' => $activity],
            'inputAudioTranscription' => new \stdClass,
            'outputAudioTranscription' => new \stdClass,
            'contextWindowCompression' => ['slidingWindow' => new \stdClass],
        ];
    }

    /** Every top-level field of the setup: all of them are locked. */
    public function fieldMask(array $setup): string
    {
        return implode(',', array_keys($setup));
    }

    public function modelName(AiConfigVersion $config): string
    {
        return 'models/'.$config->live_model;
    }
}
