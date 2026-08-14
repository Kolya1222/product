<?php

namespace roilafx\Product\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use roilafx\Product\Facades\ProductFilter;
use roilafx\Product\Services\ProductFilterService;

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

        ProductFilterService::$disableCacheClearing = true;

        $totalProducts = count($groupedProducts);

        foreach ($groupedProducts as $index => $product) {
            $startTime = microtime(true);

            try {
                // 1. Замеряем создание самого документа (товара)
                $t1 = microtime(true);
                $productId = $this->entityUpserter->upsertProduct($product, $this->dictionary, $dryRun);
                $t2 = microtime(true);

                if (!$productId) {
                    $stats['errors'][] = "Не удалось создать товар: " . ($product['system']['pagetitle'] ?? 'No Title');
                    continue;
                }

                if (!$dryRun) {
                    $affectedProductIds[] = $productId;
                }

                // 2. Замеряем сохранение общих характеристик
                $t3 = microtime(true);
                $this->entityUpserter->upsertGeneralAttributes($productId, $product['general'] ?? [], $this->dictionary, true, $dryRun);
                $t4 = microtime(true);

                // 3. Замеряем создание вариантов
                $t5 = microtime(true);
                $this->variantManager->processVariants($productId, $product['variants'] ?? [], $sessionHash, $dryRun, $this->dictionary);
                $t6 = microtime(true);

                $affectedCategories[$product['system']['parent'] ?? 0] = true;
                $stats['updated']++;

                // Логируем первые 5 товаров и каждый 50-й, чтобы не засорять лог
                if ($index < 5 || $index % 50 === 0) {
                    Log::info("ИМПОРТ ТАЙМИНГ [Товар #{$index}]: ", [
                        'title'      => $product['system']['pagetitle'] ?? 'No Title',
                        'doc_create' => round(($t2 - $t1) * 1000) . ' мс',
                        'general'    => round(($t4 - $t3) * 1000) . ' мс',
                        'variants'   => round(($t6 - $t5) * 1000) . ' мс',
                        'total'      => round(($t6 - $startTime) * 1000) . ' мс'
                    ]);
                }

            } catch (\Exception $e) {
                $stats['errors'][] = $e->getMessage();
                Log::error('Исключение при обработке товара: ' . $e->getMessage(), [
                    'product' => $product['system']['pagetitle'] ?? 'Unknown',
                ]);
            }
        }

        ProductFilterService::$disableCacheClearing = false;

        if (!$dryRun) {
            foreach (array_keys($affectedCategories) as $catId) {
                if ($catId > 0) ProductFilter::clearFilterCache($catId);
            }
        }

        if (!$dryRun && in_array($mode, ['deactivate', 'full'])) {
            $this->finalizeSync($affectedProductIds, $sessionHash, $mode);
        }

        Log::info("ИМПОРТ ЧАНКА ЗАВЕРШЕН. Всего товаров: {$totalProducts}. Общее время: " . round((microtime(true) - $startTime) * 1000) . ' мс');

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