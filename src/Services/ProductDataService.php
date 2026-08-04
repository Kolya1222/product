<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Support\Collection;

class ProductDataService
{
    public function getVariants(int $productId, bool $onlyActive = true): Collection
    {
        $query = ProductVariant::where('product_id', $productId)->orderBy('sort');

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get()->map(function ($variant) {
            return (object)[
                'id'     => $variant->id,
                'attrs'  => (object)(json_decode($variant->attrs_json, true) ?? []),
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
        $variant = ProductVariant::find($variantId);
        if (!$variant) return null;

        return (object)[
            'id'     => $variant->id,
            'attrs'  => (object)(json_decode($variant->attrs_json, true) ?? []),
            'sort'   => $variant->sort,
            'active' => $variant->active,
        ];
    }
}
