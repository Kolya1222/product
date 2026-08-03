<?php

namespace roilafx\Product\Controllers;

use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Services\ProductVariantService;
use roilafx\Product\Traits\ApiResponses;
use roilafx\Product\Models\ProductVariantAttribute;
use roilafx\Product\Models\Attribute;
use roilafx\Product\Services\AttributePresetService;
use roilafx\Product\Helpers\TvRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class VariantController
{
    use ApiResponses;

    private ProductVariantService $variantService;

    public function __construct(ProductVariantService $variantService)
    {
        $this->variantService = $variantService;
    }

    public function index(Request $request)
    {
        $productId = $request->input('product_id');
        $variants = $this->variantService->getVariantsForProduct($productId);
        return response()->json($variants);
    }

    public function create(Request $request)
    {
        $productId = $request->input('product_id');
        $attributes = $this->getProductAttributes($productId);

        $fields = [];
        foreach ($attributes as $attr) {
            $fields[] = [
                'label' => $attr->name,
                'html'  => TvRenderer::render($attr, ''),
            ];
        }

        return view('products::variant_form', [
            'variant'    => null,
            'fields'     => $fields,
            'productId'  => $productId,
        ]);
    }

    public function edit($id)
    {
        $variant = ProductVariant::with('attributeValues')->findOrFail($id);
        $attributes = $this->getProductAttributes($variant->product_id);

        $fields = [];
        foreach ($attributes as $attr) {
            $val = $variant->attributeValues->firstWhere('attribute_id', $attr->id);
            $value = $val ? $val->value : '';
            $fields[] = [
                'label' => $attr->name,
                'html'  => TvRenderer::render($attr, $value),
            ];
        }

        return view('products::variant_form', [
            'variant'    => $variant,
            'fields'     => $fields,
            'productId'  => $variant->product_id,
        ]);
    }

    public function store(Request $request)
    {
        $productId = $request->input('product_id');
        $attrs = $request->input('attrs', []);
        $this->variantService->createVariant($productId, $attrs);
        return $this->successResponse();
    }

    public function update(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);
        $this->variantService->updateVariant($variant, $request->input('attrs', []));
        return $this->successResponse();
    }

    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $productId = $variant->product_id;
        $variant->delete();

        Cache::forget('product_variants_' . $productId);
        return $this->successResponse();
    }

    private function getProductAttributes($productId)
    {
        $ids = ProductVariantAttribute::where('product_id', $productId)
            ->pluck('attribute_id');
        return Attribute::whereIn('id', $ids)->get();
    }

    public function saveAsPreset(Request $request)
    {
        $name = $request->input('name');
        $description = $request->input('description', '');
        $attributeIds = $request->input('attribute_ids', []);

        if (empty($name)) {
            return $this->errorResponse('Название пресета обязательно');
        }

        $exists = \roilafx\Product\Models\AttributePreset::where('name', $name)->exists();
        if ($exists) {
            return $this->errorResponse('Пресет с таким именем уже существует');
        }

        if (empty($attributeIds)) {
            return $this->errorResponse('Нет атрибутов для сохранения');
        }

        $attributes = [];
        foreach ($attributeIds as $index => $id) {
            $attributes[] = [
                'attribute_id' => $id,
                'sort'         => $index,
            ];
        }

        $presetService = new AttributePresetService();
        $preset = $presetService->create([
            'name'        => $name,
            'description' => $description,
            'attributes'  => $attributes,
        ]);

        return $this->successResponse(['preset' => $preset]);
    }
}
