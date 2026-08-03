<?php

namespace roilafx\Product\Facades;

use Illuminate\Support\Facades\Facade;
use roilafx\Product\Services\ProductFilterService;

class ProductFilter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ProductFilterService::class;
    }
}
