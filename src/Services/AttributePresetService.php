<?php

namespace roilafx\Product\Services;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use roilafx\Product\Facades\ProductFilter;
use roilafx\Product\Models\AttributePreset;
use roilafx\Product\Models\AttributePresetAttribute;
use roilafx\Product\Models\ProductAttribute;
use roilafx\Product\Models\ProductPreset;
use roilafx\Product\Models\ProductVariantAttribute;

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

    public function applyToProduct(int $productId, AttributePreset $preset, string $mode = 'replace', string $target = 'variant'): void
    {
        DB::transaction(function () use ($productId, $preset, $mode, $target) {
            $attributeIds = $preset->attributes()->pluck('attribute_id')->toArray();

            if ($target === 'general') {
                $model = ProductAttribute::class;
            } else {
                $model = ProductVariantAttribute::class;
            }

            if ($mode === 'replace') {
                $model::where('product_id', $productId)->delete();
            }

            foreach ($attributeIds as $attrId) {
                $model::firstOrCreate([
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
