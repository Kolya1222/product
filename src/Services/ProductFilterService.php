<?php

namespace roilafx\Product\Services;

use EvolutionCMS\Models\SiteContent;
use roilafx\Product\Models\Attribute;
use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\ProductVariantAttribute;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductFilterService
{
    protected int $cacheTtl = 3600;

    protected function applyFilterCondition($query, string $field, string $operator, $value): void
    {
        switch ($operator) {
            case 'eq':
                $query->where($field, $value);
                break;
            case 'neq':
                $query->where($field, '!=', $value);
                break;
            case 'like':
                $query->where($field, 'LIKE', '%' . $value . '%');
                break;
            case 'between':
                if (is_array($value)) {
                    if (isset($value['min']) && $value['min'] !== '') $query->where($field, '>=', (float)$value['min']);
                    if (isset($value['max']) && $value['max'] !== '') $query->where($field, '<=', (float)$value['max']);
                }
                break;
            case 'in':
                $query->whereIn($field, (array)$value);
                break;
            case 'notin':
                $query->whereNotIn($field, (array)$value);
                break;
            case 'gte':
                $query->where($field, '>=', (float)$value);
                break;
            case 'lte':
                $query->where($field, '<=', (float)$value);
                break;
            case 'gt':
                $query->where($field, '>', (float)$value);
                break;
            case 'lt':
                $query->where($field, '<', (float)$value);
                break;
            default:
                $query->where($field, $value);
        }
    }

    protected function applyActiveFiltersToQuery($query, string $productColumn, array $filters, array $filterConfig = [])
    {
        $attributeMap = Attribute::whereIn('code', array_keys($filters))->get()->keyBy('code');

        foreach ($filters as $code => $value) {
            $attr = $attributeMap->get($code);
            if (!$attr || ($value === '' && $value !== '0')) continue;

            $config   = $filterConfig[$code] ?? [];
            $operator = $config['filter']['operator'] ?? $this->defaultOperator($attr->field_type);
            $field    = $config['filter']['field']    ?? $this->defaultField($attr->field_type);

            $query->where(function ($q) use ($attr, $value, $operator, $field, $productColumn) {
                $q->whereExists(function ($subQ) use ($attr, $value, $operator, $field, $productColumn) {
                    $subQ->select(DB::raw(1))
                        ->from('product_attributes')
                        ->whereColumn('product_attributes.product_id', $productColumn)
                        ->where('product_attributes.attribute_id', $attr->id);
                    $this->applyFilterCondition($subQ, 'product_attributes.' . $field, $operator, $value);
                })
                    ->orWhereExists(function ($subQ) use ($attr, $value, $operator, $field, $productColumn) {
                        $subQ->select(DB::raw(1))
                            ->from('variant_attribute_values as vav_f')
                            ->join('product_variants as pv_f', 'pv_f.id', '=', 'vav_f.variant_id')
                            ->whereColumn('pv_f.product_id', $productColumn)
                            ->where('pv_f.active', 1)
                            ->where('vav_f.attribute_id', $attr->id);
                        $this->applyFilterCondition($subQ, 'vav_f.' . $field, $operator, $value);
                    });
            });
        }
    }

    public function getAttributesForCatalog(int $catalogId, int $depth = 0): array
    {
        $cacheKey = "filter_attributes_{$catalogId}_{$depth}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($catalogId, $depth) {
            $categoryIds = [$catalogId];
            if ($depth > 0) {
                $categoryIds = DB::table('site_content_closure')
                    ->where('ancestor', $catalogId)
                    ->where('depth', '>', 0)
                    ->where('depth', '<=', $depth)
                    ->pluck('descendant')
                    ->toArray();
                $categoryIds[] = $catalogId;
            }

            $variantAttrIds = DB::table('product_variant_attributes as pva')
                ->join('site_content as sc', 'sc.id', '=', 'pva.product_id')
                ->where('sc.published', 1)->where('sc.deleted', 0)
                ->whereIn('sc.parent', $categoryIds)
                ->pluck('pva.attribute_id')->unique()->toArray();

            $productAttrIds = DB::table('product_attributes as pa')
                ->join('site_content as sc', 'sc.id', '=', 'pa.product_id')
                ->where('sc.published', 1)->where('sc.deleted', 0)
                ->whereIn('sc.parent', $categoryIds)
                ->pluck('pa.attribute_id')->unique()->toArray();

            $attributeIds = array_unique(array_merge($variantAttrIds, $productAttrIds));

            return Attribute::whereIn('id', $attributeIds)
                ->whereNotIn('field_type', ['custom_tv:multitv'])
                ->get()->toArray();
        });
    }

    public function defaultOperator(string $fieldType): string
    {
        return in_array($fieldType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option']) ? 'in' : 'like';
    }

    public function defaultField(string $fieldType): string
    {
        return $fieldType === 'number' ? 'value_numeric' : 'value';
    }

    public function getFilterStateLight(array $allAttributes, array $activeFilters = []): array
    {
        $filterStateLight = [];
        foreach ($allAttributes as $attribute) {
            $attrCode = $attribute['code'];
            $displayType = $attribute['field_type'];
            $displayOptions = $attribute['options'] ?? [];
            $values = [];
            if (in_array($displayType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])) {
                foreach ($displayOptions as $opt) {
                    $values[] = ['value' => $opt, 'count' => 0, 'available' => true];
                }
            }
            $filterStateLight[] = [
                'id' => $attribute['id'],
                'code' => $attrCode,
                'name' => $attribute['name'],
                'type' => $displayType,
                'options' => $displayOptions,
                'values' => $values,
                'min' => null,
                'max' => null,
                'current_min' => $activeFilters[$attrCode]['min'] ?? null,
                'current_max' => $activeFilters[$attrCode]['max'] ?? null,
                'current_value' => is_array($activeFilters[$attrCode] ?? null) ? null : ($activeFilters[$attrCode] ?? null),
                'filter' => ['operator' => $this->defaultOperator($displayType), 'field' => $this->defaultField($displayType)]
            ];
        }
        return $filterStateLight;
    }

    public function getFilteredProductsWithAttributes(
        int $catalogId,
        array $filters = [],
        int $perPage = 12,
        string $sort = 'menuindex:asc',
        array $withAttributes = [],
        ?int $page = null,
        array $filterConfig = [],
        int $depth = 0
    ): LengthAwarePaginator {
        $prefix = DB::getTablePrefix();
        $query = SiteContent::query()->where('published', 1)->where('deleted', 0);

        $categoryIds = [$catalogId];
        if ($depth > 0) {
            $categoryIds = DB::table('site_content_closure')
                ->where('ancestor', $catalogId)->where('depth', '>', 0)->where('depth', '<=', $depth)
                ->pluck('descendant')->toArray();
            $categoryIds[] = $catalogId;
        }
        $query->whereIn('parent', $categoryIds);

        $query->where(function ($q) {
            $q->whereExists(function ($subQ) {
                $subQ->select(DB::raw(1))->from('product_variants')
                    ->whereColumn('product_variants.product_id', 'site_content.id')
                    ->where('product_variants.active', 1);
            })->orWhereExists(function ($subQ) {
                $subQ->select(DB::raw(1))->from('product_attributes')
                    ->whereColumn('product_attributes.product_id', 'site_content.id');
            });
        });

        $this->applyActiveFiltersToQuery($query, 'site_content.id', $filters, $filterConfig);

        $parts = explode(':', $sort);
        $sortField = $parts[0] ?: 'menuindex';
        $sortDir = strtolower($parts[1] ?? 'asc');
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        if (in_array($sortField, ['menuindex', 'pagetitle', 'published_at'])) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $attribute = Attribute::where('code', $sortField)->first();
            if ($attribute) {
                $attrId = $attribute->id;
                $isNumeric = $attribute->field_type === 'number';
                $aggFunc = $isNumeric ? 'MIN' : 'MAX';
                $valueField = $isNumeric ? 'value_numeric' : 'value';
                $query->orderByRaw("(
                    SELECT COALESCE(
                        (SELECT {$aggFunc}(vav.{$valueField}) FROM {$prefix}variant_attribute_values vav 
                         JOIN {$prefix}product_variants pv ON pv.id = vav.variant_id 
                         WHERE pv.product_id = site_content.id AND pv.active = 1 AND vav.attribute_id = {$attrId}),
                        (SELECT {$aggFunc}(pa.{$valueField}) FROM {$prefix}product_attributes pa 
                         WHERE pa.product_id = site_content.id AND pa.attribute_id = {$attrId})
                    )
                ) {$sortDir}");
            } else {
                $query->orderBy('menuindex', $sortDir);
            }
        }

        $page = $page ?: (request()->get('page', 1));
        $paginator = $query->paginate($perPage, ['site_content.*'], 'page', $page);

        $productIdsOnPage = $paginator->getCollection()->pluck('id')->toArray();
        $attributesData = $this->loadAttributesForProducts($productIdsOnPage, $withAttributes);

        $paginator->getCollection()->transform(function ($product) use ($attributesData) {
            $product->attrs = (object) ($attributesData[$product->id] ?? []);
            return $product;
        });

        return $paginator;
    }

    protected function loadAttributesForProducts(array $productIds, array $attrCodes): array
    {
        if (empty($productIds) || empty($attrCodes)) return array_fill_keys($productIds, []);

        $variants = ProductVariant::whereIn('product_id', $productIds)->where('active', 1)
            ->orderBy('sort')->get(['id', 'product_id', 'attrs_json'])
            ->groupBy('product_id')->map(fn($items) => $items->first());

        $directAttrs = DB::table('product_attributes')
            ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->whereIn('product_attributes.product_id', $productIds)
            ->whereIn('attributes.code', $attrCodes)
            ->select('product_attributes.product_id', 'attributes.code', 'product_attributes.value')
            ->get()->groupBy('product_id');

        $result = array_fill_keys($productIds, []);
        foreach ($productIds as $pid) {
            $variantAttrs = isset($variants[$pid]) && $variants[$pid]->attrs_json ? (json_decode($variants[$pid]->attrs_json, true) ?? []) : [];
            $directAttrsArr = [];
            if (isset($directAttrs[$pid])) {
                foreach ($directAttrs[$pid] as $row) $directAttrsArr[$row->code] = $row->value;
            }
            $result[$pid] = array_merge($directAttrsArr, $variantAttrs);
        }
        return $result;
    }

    public function getFilterState(int $catalogId, array $activeFilters = [], array $filterConfig = [], int $depth = 0): array
    {
        $cacheKey = "filter_state_{$catalogId}_" . md5(serialize($activeFilters) . serialize($filterConfig) . $depth);
        $this->storeCacheKeyForCategory($catalogId, $cacheKey);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($catalogId, $activeFilters, $filterConfig, $depth) {
            $allAttributes = $this->getAttributesForCatalog($catalogId, $depth);
            if (empty($allAttributes)) return [];

            $prefix = DB::getTablePrefix();
            $attrIds = array_column($allAttributes, 'id');
            $attrMapById = [];
            foreach ($allAttributes as $attr) {
                $attrMapById[$attr['id']] = $attr['code'];
            }

            $baseQuery = SiteContent::query()->where('published', 1)->where('deleted', 0);

            $categoryIds = [$catalogId];
            if ($depth > 0) {
                $categoryIds = DB::table('site_content_closure')
                    ->where('ancestor', $catalogId)->where('depth', '>', 0)->where('depth', '<=', $depth)
                    ->pluck('descendant')->toArray();
                $categoryIds[] = $catalogId;
            }
            $baseQuery->whereIn('parent', $categoryIds);

            $baseQuery->where(function ($q) {
                $q->whereExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('product_variants')
                        ->whereColumn('product_variants.product_id', 'site_content.id')
                        ->where('product_variants.active', 1);
                })->orWhereExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('product_attributes')
                        ->whereColumn('product_attributes.product_id', 'site_content.id');
                });
            });

            $this->applyActiveFiltersToQuery($baseQuery, 'site_content.id', $activeFilters, $filterConfig);

            $filteredProductIds = $baseQuery->pluck('site_content.id')->toArray();

            if (empty($filteredProductIds)) return [];

            $idsStr = implode(',', $filteredProductIds);
            $attrIdsStr = implode(',', $attrIds);

            $variantValsSql = "SELECT pv.product_id, vav.attribute_id, vav.value, vav.value_numeric 
                               FROM {$prefix}variant_attribute_values vav 
                               JOIN {$prefix}product_variants pv ON pv.id = vav.variant_id 
                               WHERE pv.product_id IN ({$idsStr}) AND pv.active = 1 AND vav.attribute_id IN ({$attrIdsStr})";

            $directValsSql = "SELECT product_id, attribute_id, value, value_numeric 
                              FROM {$prefix}product_attributes 
                              WHERE product_id IN ({$idsStr}) AND attribute_id IN ({$attrIdsStr})";

            $variantRows = DB::select($variantValsSql);
            $directRows = DB::select($directValsSql);

            $groupedStats = [];
            foreach ($allAttributes as $attr) {
                $groupedStats[$attr['id']] = ['values' => [], 'min_val' => null, 'max_val' => null];
            }

            $processRow = function ($row) use (&$groupedStats) {
                $aid = $row->attribute_id;
                $val = $row->value !== null ? $row->value : (string)$row->value_numeric;
                if ($val === '' && $row->value_numeric !== null) $val = (string)$row->value_numeric;

                if (!isset($groupedStats[$aid]['values'][$val])) {
                    $groupedStats[$aid]['values'][$val] = 0;
                }
                $groupedStats[$aid]['values'][$val]++;

                if ($row->value_numeric !== null) {
                    $numVal = (float)$row->value_numeric;
                    if ($groupedStats[$aid]['min_val'] === null || $numVal < $groupedStats[$aid]['min_val']) {
                        $groupedStats[$aid]['min_val'] = $numVal;
                    }
                    if ($groupedStats[$aid]['max_val'] === null || $numVal > $groupedStats[$aid]['max_val']) {
                        $groupedStats[$aid]['max_val'] = $numVal;
                    }
                }
            };

            foreach ($variantRows as $row) $processRow($row);
            foreach ($directRows as $row) $processRow($row);

            $dynamicOptions = [];
            $attrIdsNeedingOptions = [];
            foreach ($allAttributes as $attribute) {
                if (in_array($attribute['field_type'], ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option']) && empty($attribute['options'])) {
                    $attrIdsNeedingOptions[] = $attribute['id'];
                }
            }
            foreach ($groupedStats as $aid => $data) {
                if (in_array($aid, $attrIdsNeedingOptions)) {
                    $dynamicOptions[$aid] = array_keys($data['values']);
                }
            }

            $result = [];
            foreach ($allAttributes as $attribute) {
                $attrCode = $attribute['code'];
                $displayType = $attribute['field_type'];
                $displayOptions = $attribute['options'] ?? ($dynamicOptions[$attribute['id']] ?? []);
                $item = [
                    'id' => $attribute['id'],
                    'code' => $attrCode,
                    'name' => $attribute['name'],
                    'type' => $displayType,
                    'options' => $displayOptions,
                    'filter' => ['operator' => $this->defaultOperator($displayType), 'field' => $this->defaultField($displayType)]
                ];
                $stats = $groupedStats[$attribute['id']] ?? ['values' => [], 'min_val' => null, 'max_val' => null];

                if (in_array($displayType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])) {
                    $values = [];
                    foreach ($item['options'] as $opt) {
                        $cnt = $stats['values'][$opt] ?? 0;
                        $values[] = ['value' => $opt, 'count' => $cnt, 'available' => $cnt > 0];
                    }
                    $item['values'] = $values;
                } elseif (in_array($displayType, ['number', 'range'])) {
                    $item['min'] = $stats['min_val'];
                    $item['max'] = $stats['max_val'];
                    $item['current_min'] = $activeFilters[$attrCode]['min'] ?? null;
                    $item['current_max'] = $activeFilters[$attrCode]['max'] ?? null;
                }
                $result[] = $item;
            }
            return $result;
        });
    }

    public function getCachedFilteredProducts(
        int $catalogId,
        array $filters,
        int $perPage = 12,
        string $sort = 'menuindex:asc',
        array $withAttributes = [],
        ?int $page = null,
        array $filterConfig = [],
        int $depth = 0
    ): LengthAwarePaginator {
        $cacheKey = 'filter_results_' . $catalogId . '_' .
            md5(serialize($filters) . $perPage . $sort . implode(',', $withAttributes) . ($page ?? 1) . serialize($filterConfig) . $depth);

        $this->storeCacheKeyForCategory($catalogId, $cacheKey);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($catalogId, $filters, $perPage, $sort, $withAttributes, $page, $filterConfig, $depth) {
            return $this->getFilteredProductsWithAttributes($catalogId, $filters, $perPage, $sort, $withAttributes, $page, $filterConfig, $depth);
        });
    }

    protected function storeCacheKeyForCategory(int $catalogId, string $key): void
    {
        $registryKey = "filter_keys_registry_{$catalogId}";
        $keys = Cache::get($registryKey, []);
        if (!in_array($key, $keys)) {
            $keys[] = $key;
            Cache::put($registryKey, $keys, $this->cacheTtl + 60);
        }
    }

    public function clearFilterCache(int $catalogId): void
    {
        $registryKey = "filter_keys_registry_{$catalogId}";
        $keys = Cache::get($registryKey, []);
        foreach ($keys as $key) Cache::forget($key);
        Cache::forget($registryKey);
    }
}
