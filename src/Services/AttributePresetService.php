<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\AttributePreset;
use roilafx\Product\Models\AttributePresetAttribute;
use roilafx\Product\Models\ProductPreset;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use EvolutionCMS\Models\SiteContent;
use roilafx\Product\Facades\ProductFilter;

class AttributePresetService
{
    public function getAll(): Collection
    {
        return AttributePreset::with('attributes.attribute')->orderBy('name')->get();
    }

    public function create(array $data): AttributePreset
    {
        return DB::transaction(function () use ($data) {
            $preset = AttributePreset::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (!empty($data['attributes'])) {
                $this->syncAttributes($preset, $data['attributes']);
            }

            return $preset;
        });
    }

    public function update(AttributePreset $preset, array $data): void
    {
        DB::transaction(function () use ($preset, $data) {
            $preset->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? $preset->description,
            ]);

            if (isset($data['attributes'])) {
                $preset->attributes()->delete();
                $this->syncAttributes($preset, $data['attributes']);
            }
        });
    }

    public function delete(AttributePreset $preset): void
    {
        $preset->delete();
    }

    protected function syncAttributes(AttributePreset $preset, array $attributes): void
    {
        $rows = [];
        foreach ($attributes as $attr) {
            $rows[] = [
                'preset_id'    => $preset->id,
                'attribute_id' => $attr['attribute_id'],
                'sort'         => $attr['sort'] ?? 0,
                'is_required'  => true,
            ];
        }
        if (!empty($rows)) {
            AttributePresetAttribute::insert($rows);
        }
    }

    public function applyToProduct(int $productId, AttributePreset $preset, string $mode = 'replace'): void
    {
        DB::transaction(function () use ($productId, $preset, $mode) {
            $attributeIds = $preset->attributes()->pluck('attribute_id')->toArray();

            if ($mode === 'replace') {
                ProductVariantAttribute::where('product_id', $productId)->delete();
            }

            foreach ($attributeIds as $attrId) {
                ProductVariantAttribute::firstOrCreate([
                    'product_id' => $productId,
                    'attribute_id' => $attrId,
                ]);
            }

            ProductPreset::updateOrCreate(
                ['product_id' => $productId, 'preset_id' => $preset->id],
                ['applied_at' => now()]
            );
        });
        if ($product = SiteContent::find($productId)) {
            ProductFilter::clearFilterCache($product->parent);
        }
    }
}
