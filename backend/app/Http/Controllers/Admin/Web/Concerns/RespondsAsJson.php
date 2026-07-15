<?php

namespace App\Http\Controllers\Admin\Web\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait RespondsAsJson
{
    protected function jsonOk(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $message,
            'data' => $data,
        ], fn ($value) => $value !== null), $status);
    }

    protected function jsonFromValidation(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], 422);
    }
}
