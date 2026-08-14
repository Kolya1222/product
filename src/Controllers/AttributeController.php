<?php

namespace roilafx\Product\Controllers;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use roilafx\Product\Facades\ProductFilter;
use roilafx\Product\Helpers\TvRenderer;
use roilafx\Product\Helpers\TvTypeHelper;
use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributePresetAttribute;
use roilafx\Product\Models\ProductAttribute;
use roilafx\Product\Models\ProductVariantAttribute;
use roilafx\Product\Models\VariantAttributeValue;
use roilafx\Product\Services\AttributeService;
use roilafx\Product\Validators\StoreAttributeValidator;
use roilafx\Product\Validators\UpdateAttributeValidator;
use roilafx\Product\Responses\ApiResponse;
use roilafx\Product\Resources\CategoryResource;
use roilafx\Product\Resources\AttributeResource;

class AttributeController
{
    private AttributeService $attributeService;
    private ApiResponse $apiResponse;

    public function __construct(AttributeService $attributeService, ApiResponse $apiResponse)
    {
        $this->attributeService = $attributeService;
        $this->apiResponse = $apiResponse;
    }

    public function index(Request $request)
    {
        $productId = $request->input('product_id');
        $type = $request->input('type', 'variant');
        $categoriesData = $this->attributeService->getGroupedAttributesByProduct($productId, $type);

        $categories = collect($categoriesData)->map(function ($group) {
            $categoryObj = (object) [
                'id' => $group['category']['id'],
                'name' => $group['category']['name'],
            ];

            $category = new \stdClass();
            $category->id = $group['category']['id'];
            $category->name = $group['category']['name'];
            $category->created_at = $group['category']['created_at'] ?? null;
            $category->updated_at = $group['category']['updated_at'] ?? null;

            $category->attributes = collect($group['attributes'])->map(function ($attrData) use ($categoryObj) {
                $attr = new \stdClass();
                $attr->id = $attrData['id'];
                $attr->name = $attrData['name'];
                $attr->code = $attrData['code'];
                $attr->field_type = $attrData['field_type'];
                $attr->options = $attrData['options'];
                $attr->assigned = $attrData['assigned'] ?? false;
                $attr->value = $attrData['value'] ?? null;
                $attr->category = $categoryObj;
                return $attr;
            });

            return $category;
        });

        return $this->apiResponse->success(CategoryResource::collection($categories));
    }

    public function assign(Request $request)
    {
        $productId = $request->input('product_id');
        $attributeIds = $request->input('attribute_ids', []);
        $this->attributeService->assignAttributesToProduct($productId, $attributeIds);
        return $this->apiResponse->success(null, 204);
    }

    public function show($id)
    {
        $attribute = Attribute::findOrFail($id);
        return $this->apiResponse->success(new AttributeResource($attribute));
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            (new StoreAttributeValidator())->rules(),
            (new StoreAttributeValidator())->messages()
        );

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first());
        }

        $productId = $request->input('product_id');
        if ($productId) {
            $productExists = SiteContent::where('id', $productId)->exists();
            if (!$productExists) {
                return $this->apiResponse->error('Товар не найден', 404);
            }
        }

        $attribute = $this->attributeService->createAttribute(
            $validator->validated(),
            $productId
        );

        return $this->apiResponse->success(new AttributeResource($attribute), 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            (new UpdateAttributeValidator())->rules($id),
            (new UpdateAttributeValidator())->messages()
        );

        if ($validator->fails()) {
            return $this->apiResponse->error($validator->errors()->first());
        }

        $attribute = Attribute::findOrFail($id);
        $attribute->update($validator->validated());

        return $this->apiResponse->success(new AttributeResource($attribute));
    }

    public function types()
    {
        return $this->apiResponse->success([], 200, null, ['types' => TvTypeHelper::forSelect()]);
    }

    public function generalForm(Request $request)
    {
        $productId = $request->input('product_id');
        $categories = $this->attributeService->getGroupedAttributesByProduct($productId, 'general');

        $fields = [];
        foreach ($categories as $group) {
            foreach ($group['attributes'] as $attr) {
                if ($attr['assigned']) {
                    $attributeModel = Attribute::find($attr['id']);
                    $fields[] = [
                        'label' => $attr['name'],
                        'html'  => TvRenderer::render($attributeModel, $attr['value'] ?? ''),
                    ];
                }
            }
        }

        return view('products::general_form', [
            'fields'    => $fields,
            'productId' => $productId,
        ]);
    }

    public function saveGeneralValues(Request $request)
    {
        $productId = $request->input('product_id');
        $attrs = $request->input('attrs', []);

        $attributeMap = Attribute::whereIn('code', array_keys($attrs))->pluck('id', 'code');

        foreach ($attrs as $code => $value) {
            if (!isset($attributeMap[$code])) continue;

            ProductAttribute::updateOrCreate(
                ['product_id' => $productId, 'attribute_id' => $attributeMap[$code]],
                [
                    'value'         => $value,
                    'value_numeric' => is_numeric($value) ? (float)$value : null,
                ]
            );
        }

        if ($product = SiteContent::find($productId)) {
            ProductFilter::clearFilterCache($product->parent);
        }

        return $this->apiResponse->success();
    }

    public function assignGeneralAttributes(Request $request)
    {
        $productId = $request->input('product_id');
        $attributeIds = $request->input('attribute_ids', []);
        $this->attributeService->assignGeneralAttributesToProduct($productId, $attributeIds);
        return $this->apiResponse->success();
    }

    public function destroy($id)
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return $this->apiResponse->error('Атрибут не найден', 404);
        }

        $usedInProducts = ProductVariantAttribute::where('attribute_id', $id)->count();
        if ($usedInProducts > 0) {
            return $this->apiResponse->error('Атрибут назначен товарам. Сначала отвяжите его в настройках полей.', 422);
        }

        $usedInValues = VariantAttributeValue::where('attribute_id', $id)->count();
        if ($usedInValues > 0) {
            return $this->apiResponse->error('Атрибут используется в значениях вариантов (' . $usedInValues . ' записей). Удалите варианты или очистите значения.', 422);
        }

        $usedInPresets = AttributePresetAttribute::where('attribute_id', $id)->count();
        if ($usedInPresets > 0) {
            return $this->apiResponse->error('Атрибут используется в пресетах (' . $usedInPresets . ' шт.). Сначала удалите его из пресетов.', 422);
        }

        $attribute->delete();
        return $this->apiResponse->success(null, 204);
    }
}
