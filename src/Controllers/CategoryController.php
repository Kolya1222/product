<?php

namespace roilafx\Product\Controllers;

use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributeCategory;
use roilafx\Product\Validators\StoreCategoryValidator;
use roilafx\Product\Traits\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController
{
    use ApiResponses;

    public function index()
    {
        $categories = AttributeCategory::orderBy('name')->get();
        return $this->successResponse(['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            (new StoreCategoryValidator())->rules()
        );

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $category = AttributeCategory::create([
            'name' => $validator->validated()['name']
        ]);

        return $this->successResponse(['category' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = AttributeCategory::find($id);
        if (!$category) {
            return $this->errorResponse('Категория не найдена', 404);
        }

        $validator = Validator::make(
            $request->all(),
            (new StoreCategoryValidator())->rules()
        );

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $category->update(['name' => $validator->validated()['name']]);

        return $this->successResponse(['category' => $category]);
    }

    public function destroy($id)
    {
        $category = AttributeCategory::find($id);
        if ($category) {
            Attribute::where('category_id', $id)->update(['category_id' => null]);
            $category->delete();
        }
        return $this->successResponse();
    }
}
