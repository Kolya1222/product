<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributeCategory;
use roilafx\Product\Models\ProductVariantAttribute;
use EvolutionCMS\Models\SiteContent;
use roilafx\Product\Facades\ProductFilter;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    public function getGroupedAttributesByProduct(int $productId): array
    {
        $assignedIds = ProductVariantAttribute::where('product_id', $productId)->pluck('attribute_id')->toArray();
        $allAttributes = Attribute::with('category')->get();
        $grouped = $allAttributes->groupBy(fn($attr) => $attr->category_id ?? 0);
    
        $result = [];
        foreach ($grouped as $categoryId => $attrs) {
            if ($categoryId === 0) {
                $category = (new AttributeCategory)->forceFill(['id' => 0, 'name' => 'Без категории']);
            } else {
                $category = $attrs->first()->category;
            }
            $attrsWithAssigned = $attrs->map(function ($attr) use ($assignedIds) {
                $attrArray = $attr->toArray();
                $attrArray['assigned'] = in_array($attr->id, $assignedIds);
                return $attrArray;
            })->values()->all();
    
            $result[] = [
                'category'   => $category->toArray(),
                'attributes' => $attrsWithAssigned,
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
            
            if (!empty($toAdd)) {
                $rows = array_map(fn($attrId) => [
                    'product_id' => $productId, 
                    'attribute_id' => $attrId
                ], $toAdd);
                
                ProductVariantAttribute::insert($rows);
            }
        });

        if ($product = SiteContent::find($productId)) {
            ProductFilter::clearFilterCache($product->parent);
        }
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
