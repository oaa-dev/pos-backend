<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, ?string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function paginatedResponse(ResourceCollection $collection, ?string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $result = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            ...$result,
        ], $statusCode);
    }

    protected function errorResponse(string $message = 'An error occurred', int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
