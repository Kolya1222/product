<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    protected $table = 'product_attributes';
    protected $fillable = ['product_id', 'attribute_id', 'value', 'value_numeric'];
    public $timestamps = false;

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function product()
    {
        return $this->belongsTo(\EvolutionCMS\Models\SiteContent::class, 'product_id');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            if (isset($model->value) && is_numeric($model->value)) {
                $model->value_numeric = (float)$model->value;
            } else {
                $model->value_numeric = null;
            }
        });
    }
}
