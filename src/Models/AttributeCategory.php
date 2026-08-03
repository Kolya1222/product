<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeCategory extends Model
{
    protected $fillable = ['name'];

    public function attributes()
    {
        return $this->hasMany(Attribute::class, 'category_id');
    }
}
