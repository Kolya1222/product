<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';
    protected $fillable = ['product_id', 'sort', 'active', 'attrs_json'];

    public function attributeValues()
    {
        return $this->hasMany(VariantAttributeValue::class, 'variant_id');
    }

    public function product()
    {
        return $this->belongsTo(\EvolutionCMS\Models\SiteContent::class, 'product_id');
    }

    public function updateAttrsJson(): void
    {
        $this->attrs_json = $this->attributeValues()
            ->with('attribute')
            ->get()
            ->mapWithKeys(function ($val) {
                return [$val->attribute->code => $val->value];
            })
            ->toJson();
    }
}
