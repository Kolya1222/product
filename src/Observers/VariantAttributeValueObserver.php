<?php

namespace roilafx\Product\Observers;

use roilafx\Product\Models\VariantAttributeValue;
use roilafx\Product\Facades\ProductFilter;

class VariantAttributeValueObserver
{
    public function saved(VariantAttributeValue $value)
    {
        if ($value->variant && $variantProduct = $value->variant->product) {
            ProductFilter::clearFilterCache($variantProduct->parent);
        }
    }

    public function deleting(VariantAttributeValue $value)
    {
        if ($value->variant && $variantProduct = $value->variant->product) {
            ProductFilter::clearFilterCache($variantProduct->parent);
        }
    }
}
