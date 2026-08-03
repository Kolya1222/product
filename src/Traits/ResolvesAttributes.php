<?php

namespace roilafx\Product\Traits;

use roilafx\Product\Services\AttributeService;

trait ResolvesAttributes
{
    protected function getAllAttributesFlat(): array
    {
        $service = app(AttributeService::class);
        $grouped = $service->getGroupedAttributesByProduct(0);
        $flat = [];
        foreach ($grouped as $group) {
            foreach ($group['attributes'] as $attr) {
                $flat[] = $attr;
            }
        }
        return $flat;
    }
}