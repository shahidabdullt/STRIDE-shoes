<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAssistantAccess;
use App\Http\Requests\Assistant\AccessCodeRequest;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AccessController extends Controller
{
    public function store(AccessCodeRequest $request): Response
    {
        $expected = (string) config('assistant.access_code');

        abort_if($expected === '', 404);

        if (! hash_equals($expected, (string) $request->validated('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureAssistantAccess::SESSION_KEY, true);

        return response()->noContent();
    }
}
