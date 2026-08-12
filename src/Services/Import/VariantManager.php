<?php

namespace roilafx\Product\Services\Import;

use roilafx\Product\Services\ProductVariantService;
use Illuminate\Support\Facades\DB;
use roilafx\Product\Models\ProductVariant;

class VariantManager
{
    private ProductVariantService $variantService;

    public function __construct(ProductVariantService $variantService)
    {
        $this->variantService = $variantService;
    }

    public function processVariants(int $productId, array $variants, string $sessionHash, bool $dryRun, DictionaryIndex $dictionary): void
    {
        if (empty($variants)) return;

        foreach ($variants as $variantData) {
            $variantSku = $variantData['sku'] ?? null;
            $variantId = null;

            if ($variantSku) {
                $variantId = DB::table('product_variants as pv')
                    ->join('variant_attribute_values as vav', 'vav.variant_id', '=', 'pv.id')
                    ->join('attributes as a', 'a.id', '=', 'vav.attribute_id')
                    ->where('pv.product_id', $productId)
                    ->where('a.code', 'sku')
                    ->where('vav.value_hash', md5($variantSku))
                    ->value('pv.id');
            }

            if ($dryRun) continue;

            $attrIdsToAssign = [];
            foreach (array_keys($variantData) as $code) {
                $attrId = $dictionary->getAttributeId($code, true);
                if ($attrId) $attrIdsToAssign[] = $attrId;
            }
            
            if (!empty($attrIdsToAssign)) {
                $assignRows = [];
                foreach ($attrIdsToAssign as $id) {
                    $assignRows[] = [
                        'product_id' => $productId, 
                        'attribute_id' => $id
                    ];
                }
                DB::table('product_variant_attributes')->insertOrIgnore($assignRows);
            }

            if ($variantId) {
                $variant = ProductVariant::find($variantId);
                $this->variantService->updateVariant($variant, $variantData);
            } else {
                $variant = $this->variantService->createVariant($productId, $variantData);
            }
            
            DB::table('product_variants')->where('id', $variant->id)->update([
                'sync_hash' => $sessionHash
            ]);
        }
    }
}