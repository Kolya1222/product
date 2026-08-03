<?php

namespace roilafx\Product\Services;

use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductVariantService
{
    public function getVariantsForProduct(int $productId): array
    {
        $cacheKey = 'product_variants_' . $productId;
        return Cache::remember($cacheKey, 600, function () use ($productId) {
            return ProductVariant::where('product_id', $productId)
                ->with('attributeValues.attribute')
                ->orderBy('sort')
                ->get()
                ->map(function ($variant) {
                    $attrs = $variant->attributeValues->mapWithKeys(function ($item) {
                        return [$item->attribute->code => $item->value];
                    });
                    return ['id' => $variant->id, 'attrs' => $attrs];
                })
                ->toArray();
        });
    }


    public function createVariant(int $productId, array $attrs = []): ProductVariant
    {
        return DB::transaction(function () use ($productId, $attrs) {
            if (!empty($attrs)) {
                $assignedCodes = ProductVariantAttribute::where('product_id', $productId)
                    ->with('attribute')
                    ->get()
                    ->pluck('attribute.code')
                    ->toArray();

                $unknown = array_diff(array_keys($attrs), $assignedCodes);
                if (!empty($unknown)) {
                    \Log::warning('Попытка создать вариант с атрибутами, не назначенными товару.', [
                        'product_id' => $productId,
                        'unknown_codes' => $unknown,
                    ]);
                    throw new \InvalidArgumentException('Некоторые атрибуты не назначены товару.');
                }
            }

            $variant = ProductVariant::create([
                'product_id' => $productId,
                'sort'       => 0,
                'active'     => 1,
            ]);

            if (!empty($attrs)) {
                $this->syncAttributes($variant, $attrs);
            }

            return $variant;
        });
    }

    public function updateVariant(ProductVariant $variant, array $attrs = []): void
    {
        DB::transaction(function () use ($variant, $attrs) {
            $this->syncAttributes($variant, $attrs);
        });
        Cache::forget('product_variants_' . $variant->product_id);
    }

    public function syncAttributes(ProductVariant $variant, array $attrs): void
    {
        $variant->attributeValues()->delete();

        foreach ($attrs as $code => $value) {
            $attribute = Attribute::where('code', $code)->first();
            if (!$attribute) {
                continue;
            }

            $data = [
                'attribute_id' => $attribute->id,
                'value'        => $value,
            ];

            if ($attribute->field_type === 'number' && is_numeric($value)) {
                $data['value_numeric'] = (float) $value;
            }

            $variant->attributeValues()->create($data);
        }

        $this->updateVariantJson($variant);
    }

    private function updateVariantJson(ProductVariant $variant): void
    {
        $attrs = $variant->attributeValues()
            ->with('attribute')
            ->get()
            ->mapWithKeys(function ($val) {
                return [$val->attribute->code => $val->value];
            })
            ->toJson();

        $variant->attrs_json = $attrs;
        $variant->save();
    }
}
