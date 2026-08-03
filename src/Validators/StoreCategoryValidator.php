<?php

namespace roilafx\Product\Validators;

class StoreCategoryValidator
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}