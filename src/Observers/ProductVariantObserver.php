<?php

namespace roilafx\Product\Observers;

use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Facades\ProductFilter;

class ProductVariantObserver
{
    public function saved(ProductVariant $variant)
    {
        if ($product = $variant->product) {
            ProductFilter::clearFilterCache($product->parent);
        }
    }

    public function deleted(ProductVariant $variant)
    {
        if ($product = $variant->product) {
            ProductFilter::clearFilterCache($product->parent);
        }
    }
}
