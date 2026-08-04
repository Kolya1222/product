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

    protected function getProductIdsInCatalog(int $catalogId, int $depth = 0): \Closure
    {
        return function ($q) use ($catalogId, $depth) {
            $q->select('id')->from('site_content')
                ->where('published', 1)
                ->where('deleted', 0);

            if ($depth > 0) {
                $categoryIds = DB::table('site_content_closure')
                    ->where('ancestor', $catalogId)
                    ->where('depth', '>', 0)
                    ->where('depth', '<=', $depth)
                    ->pluck('descendant')
                    ->toArray();
                $categoryIds[] = $catalogId;
                $q->whereIn('parent', $categoryIds);
            } else {
                $q->where('parent', $catalogId);
            }

            $q->whereIn('id', function ($sq) {
                $sq->select('product_id')->from('product_variants')->where('active', 1);
            });
        };
    }

    public function getAttributesForCatalog(int $catalogId, int $depth = 0): array
    {
        $productIdsSubQuery = $this->getProductIdsInCatalog($catalogId, $depth);

        $attributeIds = ProductVariantAttribute::whereIn('product_id', $productIdsSubQuery)
            ->pluck('attribute_id')->unique()->toArray();

        return Attribute::whereIn('id', $attributeIds)
            ->whereNotIn('field_type', ['custom_tv:multitv'])
            ->get()
            ->toArray();
    }

    public function defaultOperator(string $fieldType): string
    {
        return in_array($fieldType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])
            ? 'in'
            : 'like';
    }

    public function defaultField(string $fieldType): string
    {
        return $fieldType === 'number' ? 'value_numeric' : 'value';
    }

    protected function filterProductIdsByAttributes(
        $productIds,
        array $filters,
        array $filterConfig = []
    ): array {
        if (empty($filters)) {
            return ProductVariant::whereIn('product_id', $productIds)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();
        }

        $attributeMap = Attribute::whereIn('code', array_keys($filters))->get()->keyBy('code');

        $query = ProductVariant::whereIn('product_id', $productIds)
            ->where('active', 1)
            ->select('product_variants.id');

        $joinCount = 0;
        foreach ($filters as $code => $value) {
            $attr = $attributeMap->get($code);
            if (!$attr || ($value === '' && $value !== '0')) {
                continue;
            }

            $config   = $filterConfig[$code] ?? [];
            $operator = $config['filter']['operator'] ?? $this->defaultOperator($attr->field_type);
            $field    = $config['filter']['field']    ?? $this->defaultField($attr->field_type);

            $joinCount++;
            $alias = 'vav_f' . $joinCount;

            $query->join("variant_attribute_values as {$alias}", function ($join) use ($attr, $value, $alias, $operator, $field) {
                $join->on('product_variants.id', '=', "{$alias}.variant_id")
                    ->where("{$alias}.attribute_id", $attr->id);

                switch ($operator) {
                    case 'eq':
                        $join->where("{$alias}.{$field}", $value);
                        break;
                    case 'neq':
                        $join->where("{$alias}.{$field}", '!=', $value);
                        break;
                    case 'like':
                        $join->where("{$alias}.{$field}", 'LIKE', '%' . $value . '%');
                        break;
                    case 'between':
                        if (is_array($value)) {
                            if (isset($value['min']) && $value['min'] !== '') {
                                $join->where("{$alias}.{$field}", '>=', (float)$value['min']);
                            }
                            if (isset($value['max']) && $value['max'] !== '') {
                                $join->where("{$alias}.{$field}", '<=', (float)$value['max']);
                            }
                        }
                        break;
                    case 'in':
                        $join->whereIn("{$alias}.{$field}", (array)$value);
                        break;
                    case 'notin':
                        $join->whereNotIn("{$alias}.{$field}", (array)$value);
                        break;
                    case 'gte':
                        $join->where("{$alias}.{$field}", '>=', (float)$value);
                        break;
                    case 'lte':
                        $join->where("{$alias}.{$field}", '<=', (float)$value);
                        break;
                    case 'gt':
                        $join->where("{$alias}.{$field}", '>', (float)$value);
                        break;
                    case 'lt':
                        $join->where("{$alias}.{$field}", '<', (float)$value);
                        break;
                    default:
                        $join->where("{$alias}.{$field}", $value);
                }
            });
        }

        if ($joinCount === 0) {
            return [];
        }

        return $query->pluck('id')->toArray();
    }

    public function getFilteredProductsWithAttributes(
        int    $catalogId,
        array  $filters = [],
        int    $perPage = 12,
        string $sort = 'menuindex:asc',
        array  $withAttributes = [],
        ?int   $page = null,
        array  $filterConfig = [],
        int    $depth = 0
    ): LengthAwarePaginator {
        $productIdsSubQuery = $this->getProductIdsInCatalog($catalogId, $depth);
        $variantMap = [];

        if (!empty($filters)) {
            $variantIds = $this->filterProductIdsByAttributes($productIdsSubQuery, $filters, $filterConfig);

            if (empty($variantIds)) {
                return new LengthAwarePaginator([], 0, $perPage);
            }

            $productIdsSubQuery = function ($q) use ($variantIds) {
                $q->select('product_id')->from('product_variants')->whereIn('id', $variantIds);
            };
            $variantMap = $variantIds;
        }

        $query = SiteContent::whereIn('id', $productIdsSubQuery)
            ->where('published', 1)
            ->where('deleted', 0);

        $parts = explode(':', $sort);
        $sortField = $parts[0] ?: 'menuindex';
        $sortDir   = strtolower($parts[1] ?? 'asc');
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        $allowedSortFields = ['menuindex', 'pagetitle', 'published_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $attribute = Attribute::where('code', $sortField)->first();
            if ($attribute) {
                $query->leftJoin('product_variants as sort_pv', 'site_content.id', '=', 'sort_pv.product_id')
                    ->leftJoin('variant_attribute_values as sort_vav', function ($join) use ($attribute) {
                        $join->on('sort_pv.id', '=', 'sort_vav.variant_id')
                            ->where('sort_vav.attribute_id', $attribute->id);
                    })
                    ->where('sort_pv.active', 1)
                    ->groupBy('site_content.id');

                if ($attribute->field_type === 'number') {
                    $query->orderByRaw("MIN(sort_vav.value_numeric) {$sortDir}");
                } else {
                    $query->orderByRaw("MAX(sort_vav.value) {$sortDir}");
                }
            } else {
                $query->orderBy('menuindex', $sortDir);
            }
        }

        $page = $page ?: (request()->get('page', 1));
        $paginator = $query->paginate($perPage, ['site_content.*'], 'page', $page);

        $productIdsOnPage = $paginator->getCollection()->pluck('id')->toArray();
        $attributesData   = $this->loadAttributesForProducts($productIdsOnPage, $withAttributes, $variantMap);

        $paginator->getCollection()->transform(function ($product) use ($attributesData) {
            $product->attrs = (object) ($attributesData[$product->id] ?? []);
            return $product;
        });

        return $paginator;
    }

    protected function loadAttributesForProducts(array $productIds, array $attrCodes, array $variantMap = []): array
    {
        if (empty($productIds) || empty($attrCodes)) {
            return array_fill_keys($productIds, []);
        }

        if (!empty($variantMap)) {
            $jsonValues = ProductVariant::whereIn('id', $variantMap)
                ->pluck('attrs_json', 'product_id');
        } else {
            $sub = ProductVariant::whereIn('product_id', $productIds)
                ->where('active', 1)
                ->groupBy('product_id')
                ->select('product_id', DB::raw('MIN(id) as first_id'));

            $variantIds = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub->getQuery())
                ->pluck('first_id');

            $jsonValues = ProductVariant::whereIn('id', $variantIds)
                ->pluck('attrs_json', 'product_id');
        }

        $result = array_fill_keys($productIds, []);

        foreach ($jsonValues as $productId => $json) {
            $attrs = json_decode($json, true);
            if (!is_array($attrs)) continue;

            $filtered = [];
            foreach ($attrCodes as $code) {
                if (array_key_exists($code, $attrs)) {
                    $filtered[$code] = $attrs[$code];
                }
            }
            $result[$productId] = $filtered;
        }

        return $result;
    }

    public function getFilterState(
        int   $catalogId,
        array $activeFilters = [],
        array $filterConfig = [],
        int   $depth = 0
    ): array {
        $cacheKey = "filter_state_{$catalogId}_" . md5(serialize($activeFilters) . serialize($filterConfig) . $depth);

        $this->storeCacheKeyForCategory($catalogId, $cacheKey);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($catalogId, $activeFilters, $filterConfig, $depth) {
            $productIdsSubQuery = $this->getProductIdsInCatalog($catalogId, $depth);

            $allAttributes = $this->getAttributesForCatalog($catalogId, $depth);
            if (empty($allAttributes)) return [];

            $prefix = DB::getTablePrefix();

            $dynamicOptions = [];
            $attrIdsNeedingOptions = [];
            foreach ($allAttributes as $attribute) {
                $config = $filterConfig[$attribute['code']] ?? [];
                $displayType = $config['display']['type'] ?? $attribute['field_type'];
                $explicitOptions = $config['display']['options'] ?? $attribute['options'] ?? [];
                if (
                    in_array($displayType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])
                    && empty($explicitOptions)
                ) {
                    $attrIdsNeedingOptions[] = $attribute['id'];
                }
            }

            if (!empty($attrIdsNeedingOptions)) {
                $optionsRaw = DB::table('variant_attribute_values as vav')
                    ->join('product_variants as pv', 'pv.id', '=', 'vav.variant_id')
                    ->whereIn('pv.product_id', $productIdsSubQuery)
                    ->where('pv.active', 1)
                    ->whereIn('vav.attribute_id', $attrIdsNeedingOptions)
                    ->select('vav.attribute_id', 'vav.value')
                    ->distinct()
                    ->get()
                    ->groupBy('attribute_id')
                    ->map(fn($items) => $items->pluck('value')->unique()->sort()->values()->toArray());
                $dynamicOptions = $optionsRaw->toArray();
            }

            $statsQuery = DB::table('variant_attribute_values as vav_stats')
                ->join('product_variants as pv', 'pv.id', '=', 'vav_stats.variant_id')
                ->whereIn('pv.product_id', $productIdsSubQuery)
                ->where('pv.active', 1)
                ->whereIn('vav_stats.attribute_id', array_column($allAttributes, 'id'));

            $hasActiveFilters = !empty($activeFilters);
            $whereClauses = [];

            if ($hasActiveFilters) {
                $attributeMap = Attribute::whereIn('code', array_keys($activeFilters))->get()->keyBy('code');
                $joinCount = 0;

                foreach ($activeFilters as $code => $value) {
                    $attr = $attributeMap->get($code);
                    if (!$attr || ($value === '' && $value !== '0')) continue;

                    $config   = $filterConfig[$code] ?? [];
                    $operator = $config['filter']['operator'] ?? $this->defaultOperator($attr->field_type);
                    $field    = $config['filter']['field']    ?? $this->defaultField($attr->field_type);

                    $joinCount++;
                    $alias = "vav_f{$joinCount}";
                    $attrId = $attr->id;

                    $statsQuery->leftJoin("variant_attribute_values as {$alias}", function ($join) use ($attrId, $value, $alias, $operator, $field) {
                        $join->on('pv.id', '=', "{$alias}.variant_id")
                            ->where("{$alias}.attribute_id", $attrId);

                        switch ($operator) {
                            case 'eq':
                                $join->where("{$alias}.{$field}", $value);
                                break;
                            case 'neq':
                                break;
                            case 'like':
                                $join->where("{$alias}.{$field}", 'LIKE', '%' . $value . '%');
                                break;
                            case 'between':
                                if (is_array($value)) {
                                    if (isset($value['min']) && $value['min'] !== '') $join->where("{$alias}.{$field}", '>=', (float)$value['min']);
                                    if (isset($value['max']) && $value['max'] !== '') $join->where("{$alias}.{$field}", '<=', (float)$value['max']);
                                }
                                break;
                            case 'in':
                                $join->whereIn("{$alias}.{$field}", (array)$value);
                                break;
                            case 'notin':
                                break;
                            case 'gte':
                                $join->where("{$alias}.{$field}", '>=', (float)$value);
                                break;
                            case 'lte':
                                $join->where("{$alias}.{$field}", '<=', (float)$value);
                                break;
                            case 'gt':
                                $join->where("{$alias}.{$field}", '>', (float)$value);
                                break;
                            case 'lt':
                                $join->where("{$alias}.{$field}", '<', (float)$value);
                                break;
                            default:
                                $join->where("{$alias}.{$field}", $value);
                        }
                    });

                    if (in_array($operator, ['notin', 'neq'])) {
                        $whereClauses[] = "(`{$prefix}vav_stats`.`attribute_id` = {$attrId} OR `{$prefix}{$alias}`.`variant_id` IS NULL)";
                    } else {
                        $whereClauses[] = "(`{$prefix}vav_stats`.`attribute_id` = {$attrId} OR `{$prefix}{$alias}`.`variant_id` IS NOT NULL)";
                    }
                }

                if (!empty($whereClauses)) {
                    $statsQuery->whereRaw(implode(' AND ', $whereClauses));
                }
            }

            $statsQuery->select(
                'vav_stats.attribute_id',
                'vav_stats.value',
                DB::raw("COUNT(DISTINCT {$prefix}pv.product_id) as product_count"),
                DB::raw("MIN(CAST({$prefix}vav_stats.value_numeric AS DECIMAL(14,2))) as min_val"),
                DB::raw("MAX(CAST({$prefix}vav_stats.value_numeric AS DECIMAL(14,2))) as max_val")
            )
                ->groupBy('vav_stats.attribute_id', 'vav_stats.value');

            $rawStats = $statsQuery->get();

            $groupedStats = [];
            foreach ($rawStats as $row) {
                $aid = $row->attribute_id;
                if (!isset($groupedStats[$aid])) {
                    $groupedStats[$aid] = ['values' => [], 'min_val' => null, 'max_val' => null];
                }
                $groupedStats[$aid]['values'][$row->value] = $row->product_count;
                if ($row->min_val !== null) $groupedStats[$aid]['min_val'] = $row->min_val;
                if ($row->max_val !== null) $groupedStats[$aid]['max_val'] = $row->max_val;
            }

            $result = [];
            foreach ($allAttributes as $attribute) {
                $attrId   = $attribute['id'];
                $attrCode = $attribute['code'];
                $config   = $filterConfig[$attrCode] ?? [];

                $displayType    = $config['display']['type']    ?? $attribute['field_type'];
                $displayOptions = $config['display']['options'] ?? $attribute['options'] ?? [];
                if (empty($displayOptions) && in_array($displayType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])) {
                    $displayOptions = $dynamicOptions[$attrId] ?? [];
                }

                $filterOperator = $config['filter']['operator'] ?? $this->defaultOperator($attribute['field_type']);
                $filterField    = $config['filter']['field']    ?? $this->defaultField($attribute['field_type']);
                $aggregate      = $config['aggregate'] ?? 'count';

                $item = [
                    'id'        => $attrId,
                    'code'      => $attrCode,
                    'name'      => $attribute['name'],
                    'type'      => $displayType,
                    'options'   => $displayOptions,
                    'filter'    => ['operator' => $filterOperator, 'field' => $filterField],
                    'aggregate' => $aggregate,
                ];

                $stats = $groupedStats[$attrId] ?? ['values' => [], 'min_val' => null, 'max_val' => null];

                if (in_array($displayType, ['select', 'dropdown', 'listbox', 'listbox-multiple', 'checkbox', 'option'])) {
                    $values = [];
                    foreach ($item['options'] as $optionValue) {
                        $cnt = $stats['values'][$optionValue] ?? 0;
                        $values[] = [
                            'value'     => $optionValue,
                            'count'     => $cnt,
                            'available' => $cnt > 0,
                        ];
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

    public function getFilteredProducts(
        int    $catalogId,
        array  $filters,
        int    $perPage = 12,
        string $sort = 'menuindex:asc',
        ?int   $page = null,
        array  $filterConfig = [],
        int    $depth = 0
    ): LengthAwarePaginator {
        $productIdsSubQuery = $this->getProductIdsInCatalog($catalogId, $depth);

        if (!empty($filters)) {
            $variantIds = $this->filterProductIdsByAttributes($productIdsSubQuery, $filters, $filterConfig);

            if (empty($variantIds)) {
                return new LengthAwarePaginator([], 0, $perPage);
            }

            $productIdsSubQuery = function ($q) use ($variantIds) {
                $q->select('product_id')->from('product_variants')->whereIn('id', $variantIds);
            };
        }

        $query = SiteContent::whereIn('id', $productIdsSubQuery)
            ->where('published', 1)
            ->where('deleted', 0);

        $parts = explode(':', $sort);
        $sortField = $parts[0] ?: 'menuindex';
        $sortDir   = strtolower($parts[1] ?? 'asc');
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        $allowedSortFields = ['menuindex', 'pagetitle', 'published_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $attribute = Attribute::where('code', $sortField)->first();
            if ($attribute) {
                $query->leftJoin('product_variants as sort_pv', 'site_content.id', '=', 'sort_pv.product_id')
                    ->leftJoin('variant_attribute_values as sort_vav', function ($join) use ($attribute) {
                        $join->on('sort_pv.id', '=', 'sort_vav.variant_id')
                            ->where('sort_vav.attribute_id', $attribute->id);
                    })
                    ->where('sort_pv.active', 1)
                    ->groupBy('site_content.id');

                if ($attribute->field_type === 'number') {
                    $query->orderByRaw("MIN(sort_vav.value_numeric) {$sortDir}");
                } else {
                    $query->orderByRaw("MAX(sort_vav.value) {$sortDir}");
                }
            } else {
                $query->orderBy('menuindex', $sortDir);
            }
        }

        $page = $page ?: (request()->get('page', 1));
        return $query->paginate($perPage, ['site_content.*'], 'page', $page);
    }

    public function getCachedFilteredProducts(
        int    $catalogId,
        array  $filters,
        int    $perPage = 12,
        string $sort = 'menuindex:asc',
        array  $withAttributes = [],
        ?int   $page = null,
        array  $filterConfig = [],
        int    $depth = 0
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

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($registryKey);
    }
}
