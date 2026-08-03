<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class VariantAttributeValue extends Model
{
    protected $table = 'variant_attribute_values';
    protected $fillable = ['variant_id', 'attribute_id', 'value', 'value_numeric'];
    public $timestamps = false;
    protected $casts = [
        'value_numeric' => 'float',
    ];
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
