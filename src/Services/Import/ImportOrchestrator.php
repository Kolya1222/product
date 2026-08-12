<?php

namespace roilafx\Product\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use roilafx\Product\Facades\ProductFilter;

class ImportOrchestrator
{
    private DictionaryIndex $dictionary;
    private EntityUpserter $entityUpserter;
    private VariantManager $variantManager;

    public function __construct(
        DictionaryIndex $dictionary,
        EntityUpserter $entityUpserter,
        VariantManager $variantManager
    ) {
        $this->dictionary = $dictionary;
        $this->entityUpserter = $entityUpserter;
        $this->variantManager = $variantManager;
    }

    public function processChunk(array $groupedProducts, string $mode, bool $dryRun = false): array
    {
        $sessionHash = Str::random(40);
        $stats = ['created' => 0, 'updated' => 0, 'errors' => []];
        $affectedCategories = [];
        $affectedProductIds = [];

        $this->dictionary->loadAttributes($this->extractAttrCodes($groupedProducts));

        foreach ($groupedProducts as $product) {
            $lockKey = 'import-product-' . md5(json_encode($product['unique_key'] ?? $product['system']['pagetitle']));
            $lock = Cache::lock($lockKey, 60);
            
            try {
                $lock->block(5);

                $productId = $this->entityUpserter->upsertProduct($product, $this->dictionary, $dryRun);
                if (!$productId) {
                    $stats['errors'][] = "Не удалось создать товар: " . ($product['system']['pagetitle'] ?? 'No Title');
                    Log::error('Товар не создан (upsertProduct вернул null): ', $product['system'] ?? []);
                    continue;
                }

                if (!$dryRun) {
                    $affectedProductIds[] = $productId;
                }

                $this->entityUpserter->upsertGeneralAttributes($productId, $product['general'] ?? [], $this->dictionary, true, $dryRun);
                $this->variantManager->processVariants($productId, $product['variants'] ?? [], $sessionHash, $dryRun, $this->dictionary);

                $affectedCategories[$product['system']['parent'] ?? 0] = true;
                $stats['updated']++;
                
                Log::info('Товар успешно обработан: ID ' . $productId . ' (' . ($product['system']['pagetitle'] ?? '') . ')');

            } catch (\Exception $e) {
                $stats['errors'][] = $e->getMessage();
                Log::error('Исключение при обработке товара: ' . $e->getMessage(), [
                    'product' => $product['system']['pagetitle'] ?? 'Unknown',
                ]);
            } finally {
                $lock->release();
            }
        }

        if (!$dryRun) {
            foreach (array_keys($affectedCategories) as $catId) {
                if ($catId > 0) ProductFilter::clearFilterCache($catId);
            }
        }

        if (!$dryRun && in_array($mode, ['deactivate', 'full'])) {
            $this->finalizeSync($affectedProductIds, $sessionHash, $mode);
        }

        return $stats;
    }

    public function finalizeSync(array $affectedProductIds, string $sessionHash, string $mode): void
    {
        foreach ($affectedProductIds as $productId) {
            if ($mode === 'deactivate') {
                DB::table('product_variants')
                    ->where('product_id', $productId)
                    ->where('sync_hash', '!=', $sessionHash)
                    ->update(['active' => 0]);
            } elseif ($mode === 'full') {
                $orphanVariants = DB::table('product_variants')
                    ->where('product_id', $productId)
                    ->where('sync_hash', '!=', $sessionHash)
                    ->pluck('id');

                if ($orphanVariants->isNotEmpty()) {
                    DB::table('variant_attribute_values')->whereIn('variant_id', $orphanVariants)->delete();
                    DB::table('product_variants')->whereIn('id', $orphanVariants)->delete();
                }
            }
        }
    }

    private function extractAttrCodes(array $products): array
    {
        $codes = [];
        foreach ($products as $p) {
            $codes = array_merge($codes, array_keys($p['general'] ?? []));
            foreach ($p['variants'] ?? [] as $v) {
                $codes = array_merge($codes, array_keys($v));
            }
        }
        return array_unique(array_filter($codes));
    }
}