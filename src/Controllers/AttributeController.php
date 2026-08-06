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
use roilafx\Product\Traits\ApiResponses;
use roilafx\Product\Validators\StoreAttributeValidator;
use roilafx\Product\Validators\UpdateAttributeValidator;

class AttributeController
{
    use ApiResponses;

    private AttributeService $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function index(Request $request)
    {
        $productId = $request->input('product_id');
        $type = $request->input('type', 'variant');
        $categories = $this->attributeService->getGroupedAttributesByProduct($productId, $type);
        return $this->successResponse(['categories' => $categories]);
    }

    public function assign(Request $request)
    {
        $productId = $request->input('product_id');
        $attributeIds = $request->input('attribute_ids', []);
        $this->attributeService->assignAttributesToProduct($productId, $attributeIds);
        return $this->successResponse();
    }

    public function show($id)
    {
        $attribute = Attribute::findOrFail($id);
        return $this->successResponse(['attribute' => $attribute]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            (new StoreAttributeValidator())->rules(),
            (new StoreAttributeValidator())->messages()
        );

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $productId = $request->input('product_id');
        if ($productId) {
            $productExists = SiteContent::where('id', $productId)->exists();
            if (!$productExists) {
                return $this->errorResponse('Товар не найден', 404);
            }
        }

        $attribute = $this->attributeService->createAttribute(
            $validator->validated(),
            $productId
        );

        return $this->successResponse(['attribute' => $attribute]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            (new UpdateAttributeValidator())->rules($id),
            (new UpdateAttributeValidator())->messages()
        );

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $attribute = Attribute::findOrFail($id);
        $attribute->update($validator->validated());

        return $this->successResponse(['attribute' => $attribute]);
    }

    public function types()
    {
        return $this->successResponse(['types' => TvTypeHelper::forSelect()]);
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

        return $this->successResponse();
    }

    public function assignGeneralAttributes(Request $request)
    {
        $productId = $request->input('product_id');
        $attributeIds = $request->input('attribute_ids', []);
        $this->attributeService->assignGeneralAttributesToProduct($productId, $attributeIds);
        return $this->successResponse();
    }

    public function destroy($id)
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return $this->errorResponse('Атрибут не найден', 404);
        }

        $usedInProducts = ProductVariantAttribute::where('attribute_id', $id)->count();
        if ($usedInProducts > 0) {
            return $this->errorResponse('Атрибут назначен товарам. Сначала отвяжите его в настройках полей.', 422);
        }

        $usedInValues = VariantAttributeValue::where('attribute_id', $id)->count();
        if ($usedInValues > 0) {
            return $this->errorResponse('Атрибут используется в значениях вариантов (' . $usedInValues . ' записей). Удалите варианты или очистите значения.', 422);
        }

        $usedInPresets = AttributePresetAttribute::where('attribute_id', $id)->count();
        if ($usedInPresets > 0) {
            return $this->errorResponse('Атрибут используется в пресетах (' . $usedInPresets . ' шт.). Сначала удалите его из пресетов.', 422);
        }

        $attribute->delete();
        return $this->successResponse();
    }
}
