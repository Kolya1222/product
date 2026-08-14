<?php

namespace roilafx\Product\Controllers;

use roilafx\Product\Services\ProductFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use roilafx\Product\Responses\ApiResponse;
use roilafx\Product\Resources\ProductResource;

class CatalogFilterController
{
    protected ProductFilterService $filterService;
    private ApiResponse $apiResponse;

    public function __construct(ProductFilterService $filterService, ApiResponse $apiResponse)
    {
        $this->filterService = $filterService;
        $this->apiResponse = $apiResponse;
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

        $items = $paginator->getCollection()->map(fn($product) => new ProductResource($product));
        $paginator->setCollection($items);

        return $this->apiResponse->paginated($paginator);
    }

    public function filterState(Request $request, int $catalogId): JsonResponse
    {
        $filters = $request->input('filters', []);
        $depth = (int) $request->input('depth', 0);
        $filterConfig = $request->input('filter_config', []);

        $state = $this->filterService->getFilterState($catalogId, $filters, $filterConfig, $depth);

        return $this->apiResponse->success(['state' => $state]);
    }
}