<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiConfigRequest;
use App\Models\AiConfigVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiConfigController extends Controller
{
    public function index(): View
    {
        $active = AiConfigVersion::active();

        return view('admin.ai-config.index', [
            'active' => $active,
            'versions' => AiConfigVersion::newestFirst()->paginate(20),
            // The form starts from the active version so a change is a small edit, not a rewrite.
            'draft' => $active ?? new AiConfigVersion([
                'text_model' => config('assistant.default_text_model'),
                'live_model' => config('assistant.default_live_model'),
                'temperature' => 0.4,
                'max_output_tokens' => 1024,
                'vad_silence_ms' => 800,
                'chat_enabled' => true,
                'voice_enabled' => true,
                'voice_max_seconds' => 600,
            ]),
        ]);
    }

    public function store(AiConfigRequest $request): RedirectResponse
    {
        $version = DB::transaction(function () use ($request): AiConfigVersion {
            $version = new AiConfigVersion($request->safe()->except(['activate', 'notes']));
            $version->fill([
                'version' => AiConfigVersion::nextVersionNumber(),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ])->save();

            if ($request->boolean('activate')) {
                $version->activate();
            }

            return $version;
        });

        return redirect()->route('admin.ai-config.index')->with(
            'status',
            "Version {$version->version} saved".($version->is_active ? ' and activated.' : '. It is not active yet.'),
        );
    }

    public function activate(Request $request, AiConfigVersion $version): RedirectResponse
    {
        $version->activate();

        return redirect()->route('admin.ai-config.index')->with('status', "Version {$version->version} is now active.");
    }
}
