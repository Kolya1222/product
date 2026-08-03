<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPreset extends Model
{
    protected $table = 'product_presets';
    protected $fillable = ['product_id', 'preset_id', 'applied_at'];
    public $timestamps = false;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->applied_at)) {
                $model->applied_at = now();
            }
        });
    }

    public function preset()
    {
        return $this->belongsTo(AttributePreset::class, 'preset_id');
    }

    public function product()
    {
        return $this->belongsTo(\EvolutionCMS\Models\SiteContent::class, 'product_id');
    }
}
