<?php

namespace roilafx\Product\Facades;

use Illuminate\Support\Facades\Facade;

class ProductData extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'product.data';
    }
}
