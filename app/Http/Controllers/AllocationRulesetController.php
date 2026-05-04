<?php

namespace App\Http\Controllers;

use App\Models\AllocationRuleset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllocationRulesetController extends Controller
{
    public function index(): View
    {
        $rulesets = AllocationRuleset::orderByDesc('is_active')->orderBy('name')->get();

        return view('allocation-rulesets.index', compact('rulesets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'allocation_type'    => 'required|in:household_size,family_count',
            'description'        => 'nullable|string|max:500',
            'is_active'          => 'boolean',
            'max_household_size' => 'required|integer|min:1|max:99',
            'rules'              => 'required|array|min:1',
            'rules.*.min'        => 'required|integer|min:1',
            'rules.*.max'        => 'nullable|integer|min:1',
            'rules.*.bags'       => 'required|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['rules']     = $this->normalizeRules($data['rules']);

        AllocationRuleset::create($data);

        return redirect()->route('allocation-rulesets.index')
            ->with('success', 'Ruleset created successfully.');
    }

    public function update(Request $request, AllocationRuleset $allocationRuleset): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'allocation_type'    => 'required|in:household_size,family_count',
            'description'        => 'nullable|string|max:500',
            'is_active'          => 'boolean',
            'max_household_size' => 'required|integer|min:1|max:99',
            'rules'              => 'required|array|min:1',
            'rules.*.min'        => 'required|integer|min:1',
            'rules.*.max'        => 'nullable|integer|min:1',
            'rules.*.bags'       => 'required|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['rules']     = $this->normalizeRules($data['rules']);

        $allocationRuleset->update($data);

        return redirect()->route('allocation-rulesets.index')
            ->with('success', 'Ruleset updated successfully.');
    }

    public function destroy(AllocationRuleset $allocationRuleset): RedirectResponse
    {
        $allocationRuleset->delete();

        return redirect()->route('allocation-rulesets.index')
            ->with('success', 'Ruleset deleted.');
    }

    public function preview(Request $request, AllocationRuleset $allocationRuleset): JsonResponse
    {
        $size = (int) $request->query('size', 1);
        $bags = $allocationRuleset->getBagsFor($size);

        return response()->json(['bags' => $bags, 'size' => $size]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function normalizeRules(array $rules): array
    {
        return collect($rules)
            ->map(fn($r) => [
                'min'  => (int) $r['min'],
                'max'  => isset($r['max']) && $r['max'] !== '' && $r['max'] !== null ? (int) $r['max'] : null,
                'bags' => (int) $r['bags'],
            ])
            ->sortBy('min')
            ->values()
            ->all();
    }
}
