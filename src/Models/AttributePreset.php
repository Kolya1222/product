<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class AttributePreset extends Model
{
    protected $fillable = ['name', 'description'];

    public function attributes()
    {
        return $this->hasMany(AttributePresetAttribute::class, 'preset_id');
    }
}
