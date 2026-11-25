<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModifierController extends Controller
{
    public function index()
    {
        $groups = ModifierGroup::with(['modifiers' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard/Modifiers/Index', [
            'groups' => $groups,
        ]);
    }

    public function storeGroup(Request $request)
    {
        ModifierGroup::create($this->groupPayload($request));

        return back()->with('success', 'Grup modifier berhasil ditambahkan.');
    }

    public function updateGroup(Request $request, ModifierGroup $modifierGroup)
    {
        $modifierGroup->update($this->groupPayload($request));

        return back()->with('success', 'Grup modifier berhasil diperbarui.');
    }

    public function destroyGroup(ModifierGroup $modifierGroup)
    {
        $modifierGroup->delete();

        return back()->with('success', 'Grup modifier berhasil dihapus.');
    }

    public function storeOption(Request $request)
    {
        Modifier::create($this->optionPayload($request));

        return back()->with('success', 'Opsi modifier berhasil ditambahkan.');
    }

    public function updateOption(Request $request, Modifier $modifier)
    {
        $modifier->update($this->optionPayload($request));

        return back()->with('success', 'Opsi modifier berhasil diperbarui.');
    }

    public function destroyOption(Modifier $modifier)
    {
        $modifier->delete();

        return back()->with('success', 'Opsi modifier berhasil dihapus.');
    }

    private function groupPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'selection_type' => ['required', 'in:single,multiple'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'selection_type' => $validated['selection_type'],
            'min_select' => (int) ($validated['min_select'] ?? 0),
            'max_select' => $validated['selection_type'] === 'single'
                ? 1
                : (isset($validated['max_select']) ? (int) $validated['max_select'] : null),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function optionPayload(Request $request): array
    {
        $validated = $request->validate([
            'modifier_group_id' => ['required', 'exists:modifier_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'price_delta' => ['required', 'integer', 'min:-1000000', 'max:1000000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'modifier_group_id' => (int) $validated['modifier_group_id'],
            'name' => $validated['name'],
            'price_delta' => (int) $validated['price_delta'],
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
