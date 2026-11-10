<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EvaluationCriterionRequest;
use App\Models\EvaluationCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EvaluationCriterionController extends Controller
{
    public function index(): View
    {
        return view('admin.evaluation-criteria.index', [
            'criteria' => EvaluationCriterion::orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.evaluation-criteria.form', [
            'criterion' => new EvaluationCriterion(['scale' => 'pass_fail', 'applies_to' => 'all', 'is_active' => true, 'sort' => 0]),
        ]);
    }

    public function store(EvaluationCriterionRequest $request): RedirectResponse
    {
        EvaluationCriterion::create($request->attributesForSave());

        return redirect()->route('admin.evaluation-criteria.index')->with('status', 'Criterion created. It is applied to conversations that end from now on.');
    }

    public function edit(EvaluationCriterion $evaluationCriterion): View
    {
        return view('admin.evaluation-criteria.form', ['criterion' => $evaluationCriterion]);
    }

    public function update(EvaluationCriterionRequest $request, EvaluationCriterion $evaluationCriterion): RedirectResponse
    {
        $evaluationCriterion->update($request->attributesForSave());

        return redirect()->route('admin.evaluation-criteria.index')->with('status', 'Criterion saved.');
    }

    public function destroy(EvaluationCriterion $evaluationCriterion): RedirectResponse
    {
        $evaluationCriterion->delete();

        return redirect()->route('admin.evaluation-criteria.index')->with('status', 'Criterion deleted, with its results.');
    }
}
