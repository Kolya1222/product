<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = ['name', 'code', 'field_type', 'options', 'category_id'];
    protected $casts = ['options' => 'array'];
    public function category()
    {
        return $this->belongsTo(AttributeCategory::class, 'category_id');
    }
}
