<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Support\Collection;

class ProductDataService
{
    public function getVariants(int $productId, bool $onlyActive = true): Collection
    {
        $query = ProductVariant::where('product_id', $productId)
            ->with(['attributeValues.attribute'])
            ->orderBy('sort');

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get()->map(function ($variant) {
            $attrs = [];
            foreach ($variant->attributeValues as $val) {
                $attrs[$val->attribute->code] = $val->value;
            }
            return (object)[
                'id'     => $variant->id,
                'attrs'  => (object)$attrs,
                'sort'   => $variant->sort,
                'active' => $variant->active,
            ];
        });
    }

    public function getProductVariantAttributes(int $productId): Collection
    {
        $ids = ProductVariantAttribute::where('product_id', $productId)
            ->pluck('attribute_id');

        return \roilafx\Product\Models\Attribute::whereIn('id', $ids)->get();
    }

    public function getVariant(int $variantId): ?object
    {
        $variant = ProductVariant::with('attributeValues.attribute')->find($variantId);
        if (!$variant) return null;

        $attrs = [];
        foreach ($variant->attributeValues as $val) {
            $attrs[$val->attribute->code] = $val->value;
        }

        return (object)[
            'id'     => $variant->id,
            'attrs'  => (object)$attrs,
            'sort'   => $variant->sort,
            'active' => $variant->active,
        ];
    }
}
