<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ApiResponseTrait
{
    protected function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function errorResponse(string $message = 'An error occurred', $errors = null, int $status = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    protected function validateRequest(array $data, array $rules, array $messages = []): bool
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    protected function handleException(\Exception $e, string $context = 'API operation'): JsonResponse
    {
        Log::error("{$context} failed", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_id' => request()->header('X-Request-ID', 'unknown'),
        ]);

        $message = app()->environment('production')
            ? 'An error occurred while processing your request'
            : $e->getMessage();

        return $this->errorResponse($message, null, 500);
    }

    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse("{$resource} not found", null, 404);
    }
}
