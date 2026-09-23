<?php

namespace App\Http\Controllers\API\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $message = $message ?? __('Success');
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response
     *
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(?string $message = null, int $code = 400, $data = null): JsonResponse
    {
        $message = $message ?? __('Error');
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Not found response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFound(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('Not found'), 404);
    }

    /**
     * Unauthorized response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorized(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('Unauthorized'), 401);
    }

    /**
     * Forbidden response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbidden(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('Forbidden'), 403);
    }

    /**
     * Validation error response
     *
     * @param string $message
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationError(?string $message = null, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 422,
            'message' => $message ?? __('Validation failed'),
            'data' => $errors,
        ], 422);
    }

    /**
     * Created response
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function created($data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? __('Created'), 201);
    }

    /**
     * Updated response
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updated($data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? __('Updated'));
    }

    /**
     * Deleted response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deleted(?string $message = null): JsonResponse
    {
        return $this->success(null, $message ?? __('Deleted'));
    }
}