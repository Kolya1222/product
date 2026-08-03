<?php

namespace roilafx\Product\Controllers;

use roilafx\Product\Services\ProductFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatalogFilterController
{
    protected ProductFilterService $filterService;

    public function __construct(ProductFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    public function filter(Request $request, int $catalogId): JsonResponse
    {
        $filters = $request->input('filters', []);
        $perPage = (int) $request->input('per_page', 12);
        $sort = $request->input('sort', 'menuindex:asc');
        $page = $request->input('page', 1);
        $depth = (int) $request->input('depth', 0); 

        $filterConfig = $request->input('filter_config', []);

        $allAttributes = $this->filterService->getAttributesForCatalog($catalogId, $depth);
        $withAttributes = array_column($allAttributes, 'code');

        $paginator = $this->filterService->getCachedFilteredProducts(
            $catalogId,
            $filters,
            $perPage,
            $sort,
            $withAttributes,
            $page,
            $filterConfig,
            $depth
        );

        $paginator->appends([
            'filters'  => $filters,
            'sort'     => $sort,
            'per_page' => $perPage,
        ]);

        $items = $paginator->getCollection()->map(function ($product) {
            return [
                'id'        => $product->id,
                'title'     => $product->pagetitle,
                'alias'     => $product->alias,
                'introtext' => $product->introtext,
                'url'       => evo()->makeUrl($product->id),
                'attrs'     => $product->attrs,
            ];
        });

        return response()->json([
            'success' => true,
            'items'   => $items,
            'pagination' => [
                'current_page'   => $paginator->currentPage(),
                'last_page'      => $paginator->lastPage(),
                'total'          => $paginator->total(),
                'per_page'       => $paginator->perPage(),
                'next_page_url'  => $paginator->nextPageUrl(),
                'prev_page_url'  => $paginator->previousPageUrl(),
                'first_page_url' => $paginator->url(1),
                'last_page_url'  => $paginator->url($paginator->lastPage()),
            ],
        ]);
    }

    public function filterState(Request $request, int $catalogId): JsonResponse
    {
        $filters = $request->input('filters', []);
        $depth = (int) $request->input('depth', 0); 
        $filterConfig = $request->input('filter_config', []);

        $state = $this->filterService->getFilterState($catalogId, $filters, $filterConfig, $depth);

        return response()->json(['success' => true, 'state' => $state]);
    }
}