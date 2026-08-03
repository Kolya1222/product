<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributeCategory;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    public function getGroupedAttributesByProduct(int $productId): array
    {
        $assigned = ProductVariantAttribute::where('product_id', $productId)->pluck('attribute_id');
        $attributes = Attribute::with('category')->get()->each(function ($attr) use ($assigned) {
            $attr->assigned = $assigned->contains($attr->id);
        });

        $grouped = $attributes->groupBy(fn($attr) => $attr->category_id ?? 0);

        $result = [];
        foreach ($grouped as $categoryId => $attrs) {
            if ($categoryId === 0) {
                $category = (new AttributeCategory)->forceFill(['id' => 0, 'name' => 'Без категории']);
            } else {
                $category = AttributeCategory::find($categoryId);
            }

            $result[] = [
                'category'   => $category->toArray(),
                'attributes' => $attrs->values(),
            ];
        }

        return $result;
    }

    public function assignAttributesToProduct(int $productId, array $attributeIds): void
    {
        $currentIds = ProductVariantAttribute::where('product_id', $productId)->pluck('attribute_id')->toArray();
        $toDelete = array_diff($currentIds, $attributeIds);
        $toAdd = array_diff($attributeIds, $currentIds);

        DB::transaction(function () use ($productId, $toDelete, $toAdd) {
            if (!empty($toDelete)) {
                ProductVariantAttribute::where('product_id', $productId)
                    ->whereIn('attribute_id', $toDelete)
                    ->delete();
            }
            foreach ($toAdd as $attrId) {
                ProductVariantAttribute::create([
                    'product_id'   => $productId,
                    'attribute_id' => $attrId,
                ]);
            }
        });
    }

    public function createAttribute(array $data, ?int $productId = null): Attribute
    {
        $attribute = Attribute::create([
            'name'        => $data['name'],
            'code'        => $data['code'],
            'field_type'  => $data['field_type'],
            'options'     => $data['options'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ]);

        if ($productId) {
            ProductVariantAttribute::firstOrCreate([
                'product_id'   => $productId,
                'attribute_id' => $attribute->id,
            ]);
        }

        return $attribute;
    }
}
