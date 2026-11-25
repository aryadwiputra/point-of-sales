<?php

namespace App\Services;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierOverride;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ModifierService
{
    public function attachableGroups(Product $product): Collection
    {
        $product->loadMissing(['modifierGroups.modifiers', 'modifierOverrides']);

        $overrides = $product->modifierOverrides->keyBy('modifier_id');

        return $product->modifierGroups
            ->filter(fn (ModifierGroup $group) => $group->is_active && (bool) $group->pivot->is_active)
            ->map(function (ModifierGroup $group) use ($overrides) {
                $options = $group->modifiers
                    ->filter(function (Modifier $modifier) use ($overrides) {
                        if (! $modifier->is_active) {
                            return false;
                        }
                        $override = $overrides->get($modifier->id);

                        return ! $override || $override->is_active;
                    })
                    ->map(function (Modifier $modifier) use ($overrides) {
                        $modifier->effective_price_delta = $this->effectiveDelta($overrides->get($modifier->id), $modifier);

                        return $modifier;
                    })
                    ->values();

                $group->setRelation('modifiers', $options);

                return $group;
            })
            ->filter(fn (ModifierGroup $group) => $group->modifiers->isNotEmpty())
            ->values();
    }

    public function effectiveDelta(?ProductModifierOverride $override, Modifier $modifier): int
    {
        return (int) ($override?->price_delta ?? $modifier->price_delta);
    }

    public function selectionSnapshot(Product $product, array $modifierIds): array
    {
        $groups = $this->attachableGroups($product);
        $selectedIds = collect($modifierIds)->map(fn ($id) => (int) $id)->unique()->values();

        $allowed = $groups
            ->flatMap(fn (ModifierGroup $group) => $group->modifiers->map(fn (Modifier $modifier) => [
                'group' => $group,
                'modifier' => $modifier,
            ]))
            ->keyBy(fn (array $row) => $row['modifier']->id);

        foreach ($selectedIds as $id) {
            if (! $allowed->has($id)) {
                throw ValidationException::withMessages([
                    'modifier_ids' => "Modifier tidak valid untuk produk {$product->title}.",
                ]);
            }
        }

        foreach ($groups as $group) {
            $count = $group->modifiers->whereIn('id', $selectedIds->all())->count();
            $min = (int) $group->min_select;
            $max = $group->isSingle() ? 1 : $group->max_select;

            if ($count < $min) {
                throw ValidationException::withMessages([
                    'modifier_ids' => "Pilih minimal {$min} opsi untuk {$group->name}.",
                ]);
            }

            if ($max !== null && $count > $max) {
                throw ValidationException::withMessages([
                    'modifier_ids' => "Maksimal {$max} opsi untuk {$group->name}.",
                ]);
            }
        }

        return $selectedIds
            ->map(function (int $id) use ($allowed) {
                $row = $allowed->get($id);

                return [
                    'modifier_id' => $row['modifier']->id,
                    'modifier_group_id' => $row['group']->id,
                    'group_name' => $row['group']->name,
                    'name' => $row['modifier']->name,
                    'price_delta' => (int) $row['modifier']->effective_price_delta,
                ];
            })
            ->sortBy(fn (array $item) => $item['modifier_group_id'].'-'.$item['modifier_id'])
            ->values()
            ->all();
    }

    public function deltaFor(array $snapshot): int
    {
        return (int) array_sum(array_column($snapshot, 'price_delta'));
    }

    public function syncProductGroups(Product $product, array $groupIds): void
    {
        $sync = collect($groupIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index, 'is_active' => true]])
            ->all();

        $product->modifierGroups()->sync($sync);
    }

    public function syncOverrides(Product $product, array $modifierIdToDelta): void
    {
        $product->modifierOverrides()->delete();

        foreach ($modifierIdToDelta as $modifierId => $delta) {
            if ($delta === null || $delta === '') {
                continue;
            }

            ProductModifierOverride::create([
                'product_id' => $product->id,
                'modifier_id' => (int) $modifierId,
                'price_delta' => (int) $delta,
                'is_active' => true,
            ]);
        }
    }
}
