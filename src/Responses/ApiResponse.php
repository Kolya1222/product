<?php

namespace roilafx\Product\Responses;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public function success(mixed $data = [], int $status = 200, ?array $headers = null, ?array $meta = null, ?array $links = null): JsonResponse
    {
        if ($status === 204) {
            return response()->json(null, 204);
        }

        $response = [];

        if ($data !== null) {
            $response["data"] = $data;
        }
        if ($meta) {
            $response["meta"] = $meta;
        }
        if ($links) {
            $response["links"] = $links;
        }
        return response()->json($response, $status, $headers ?? []);
    }

    public function error(string $message, int $status = 422, ?string $code = null, ?array $details = null): JsonResponse
    {
        $error = [];
        $error['title'] = $message;
        if ($code) {
            $error['code'] = $code;
        }
        if ($details) {
            $error['details'] = $details;
        }
        return response()->json(['errors' => [$error]], $status);
    }

    public function paginated(LengthAwarePaginator $paginated): JsonResponse 
    {
        $items = $paginated->items();

        $meta = [
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ];

        $links = [
            'self' => $paginated->url($paginated->currentPage()),
            'first' => $paginated->url(1),
            'last' => $paginated->url($paginated->lastPage()),
            'prev' => $paginated->previousPageUrl(),
            'next' => $paginated->nextPageUrl(),
        ];
        return $this->success($items, 200, null, $meta, $links);
    }
}
