<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DataPointDefinitionRequest;
use App\Models\DataPointDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DataPointDefinitionController extends Controller
{
    public function index(): View
    {
        return view('admin.data-points.index', [
            'definitions' => DataPointDefinition::withCount('values')->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.data-points.form', ['definition' => new DataPointDefinition(['type' => 'string', 'is_active' => true, 'sort' => 0])]);
    }

    public function store(DataPointDefinitionRequest $request): RedirectResponse
    {
        DataPointDefinition::create($request->attributesForSave());

        return redirect()->route('admin.data-points.index')->with('status', 'Data point created. It is extracted from conversations that end from now on.');
    }

    public function edit(DataPointDefinition $dataPoint): View
    {
        return view('admin.data-points.form', ['definition' => $dataPoint]);
    }

    public function update(DataPointDefinitionRequest $request, DataPointDefinition $dataPoint): RedirectResponse
    {
        $dataPoint->update($request->attributesForSave());

        return redirect()->route('admin.data-points.index')->with('status', 'Data point saved.');
    }

    public function destroy(DataPointDefinition $dataPoint): RedirectResponse
    {
        $dataPoint->delete();

        return redirect()->route('admin.data-points.index')->with('status', 'Data point deleted, with its values.');
    }
}
