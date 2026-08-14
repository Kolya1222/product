<?php

namespace roilafx\Product\Controllers;

use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributeCategory;
use roilafx\Product\Validators\StoreCategoryValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use roilafx\Product\Responses\ApiResponse;
use roilafx\Product\Resources\CategoryResource;

class CategoryController
{
    private ApiResponse $apiResponse;

    public function __construct(ApiResponse $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    public function index()
    {
        $categories = AttributeCategory::orderBy('name')->get();
        return $this->apiResponse->success(CategoryResource::collection($categories));
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            (new StoreCategoryValidator())->rules()
        );

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first());
        }

        $category = AttributeCategory::create([
            'name' => $validator->validated()['name']
        ]);

        return $this->apiResponse->success(new CategoryResource($category), 201);
    }

    public function update(Request $request, $id)
    {
        $category = AttributeCategory::find($id);
        if (!$category) {
            return $this->apiResponse->error('Категория не найдена', 404);
        }

        $validator = Validator::make(
            $request->all(),
            (new StoreCategoryValidator())->rules()
        );

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first());
        }

        $category->update(['name' => $validator->validated()['name']]);

        return $this->apiResponse->success(new CategoryResource($category));
    }

    public function destroy($id)
    {
        $category = AttributeCategory::find($id);
        if ($category) {
            Attribute::where('category_id', $id)->update(['category_id' => null]);
            $category->delete();
        }
        return $this->apiResponse->success(null, 204);
    }
}