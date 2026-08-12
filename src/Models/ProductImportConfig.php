<?php

namespace roilafx\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportConfig extends Model
{
    protected $table = 'product_import_configs';
    protected $fillable = ['name', 'source_type', 'mapping', 'transformers', 'sync_mode', 'create_attrs'];
    
    protected $casts = [
        'mapping' => 'array',
        'transformers' => 'array',
    ];
}