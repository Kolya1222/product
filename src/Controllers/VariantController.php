<?php

namespace roilafx\Product\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use roilafx\Product\Helpers\TvRenderer;
use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\AttributePreset;
use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\ProductVariantAttribute;
use roilafx\Product\Services\AttributePresetService;
use roilafx\Product\Services\ProductVariantService;
use roilafx\Product\Responses\ApiResponse;
use roilafx\Product\Resources\VariantResource;
use roilafx\Product\Resources\PresetResource;

class VariantController
{
    private ProductVariantService $variantService;
    private ApiResponse $apiResponse;

    public function __construct(ProductVariantService $variantService, ApiResponse $apiResponse)
    {
        $this->variantService = $variantService;
        $this->apiResponse = $apiResponse;
    }

    public function index(Request $request)
    {
        $productId = $request->input('product_id');
        $variants = $this->variantService->getVariantsForProduct($productId);
        return $this->apiResponse->success(VariantResource::collection($variants));
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
        $variant = $this->variantService->createVariant($productId, $attrs);
        return $this->apiResponse->success(new VariantResource($variant), 201);
    }

    public function update(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);
        $this->variantService->updateVariant($variant, $request->input('attrs', []));
        $variant->refresh();
        return $this->apiResponse->success(new VariantResource($variant));
    }

    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $productId = $variant->product_id;
        $variant->delete();

        Cache::forget('product_variants_' . $productId);
        return $this->apiResponse->success(null, 204);
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
            return $this->apiResponse->error('Название пресета обязательно');
        }

        $exists = AttributePreset::where('name', $name)->exists();
        if ($exists) {
            return $this->apiResponse->error('Пресет с таким именем уже существует');
        }

        if (empty($attributeIds)) {
            return $this->apiResponse->error('Нет атрибутов для сохранения');
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

        return $this->apiResponse->success(new PresetResource($preset), 201);
    }
}