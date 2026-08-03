<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class AttributePresetAttribute extends Model
{
    protected $table = 'attribute_preset_attributes';
    protected $fillable = ['preset_id', 'attribute_id', 'sort', 'is_required', 'generation_config'];
    protected $casts = [
        'generation_config' => 'array',
    ];
    public $timestamps = false;

    public function preset()
    {
        return $this->belongsTo(AttributePreset::class, 'preset_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
}
