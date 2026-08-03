<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantAttribute extends Model
{
    protected $table = 'product_variant_attributes';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function product()
    {
        return $this->belongsTo(\EvolutionCMS\Models\SiteContent::class, 'product_id');
    }
}
