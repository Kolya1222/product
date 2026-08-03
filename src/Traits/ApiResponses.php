<?php

namespace roilafx\Product\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    protected function successResponse($data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], $data), $status);
    }

    protected function errorResponse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}