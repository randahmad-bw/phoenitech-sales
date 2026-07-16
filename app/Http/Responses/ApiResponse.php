<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

/**
 * Unified API response factory for all JSON responses.
 * Ensures every endpoint returns a consistent envelope structure.
 */
class ApiResponse
{
    /**
     * Return a success response with optional data.
     */
    public static function success(mixed $data = null, string $message = 'Operation completed successfully.', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a 201 created response with the created resource data.
     */
    public static function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    /**
     * Return a paginated resource collection with meta information.
     */
    public static function paginated(ResourceCollection $collection, string $message = 'Data retrieved.'): JsonResponse
    {
        $paginated = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginated['data'],
            'meta' => [
                'current_page' => $paginated['meta']['current_page'] ?? null,
                'last_page' => $paginated['meta']['last_page'] ?? null,
                'per_page' => $paginated['meta']['per_page'] ?? null,
                'total' => $paginated['meta']['total'] ?? null,
            ],
        ]);
    }

    /**
     * Return an error response with a message and optional error code.
     */
    public static function error(string $message, ?string $errorCode = null, int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response (422) with field-level errors.
     */
    public static function validationError(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Return a 404 not found response.
     */
    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return static::error($message, 'NOT_FOUND', 404);
    }

    /**
     * Return a 401 unauthorized response.
     */
    public static function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return static::error($message, 'UNAUTHORIZED', 401);
    }

    /**
     * Return a 403 forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return static::error($message, 'FORBIDDEN', 403);
    }

    /**
     * Return a 409 conflict response for business rule violations.
     */
    public static function conflict(string $message, ?string $errorCode = null): JsonResponse
    {
        return static::error($message, $errorCode, 409);
    }

    /**
     * Convert an exception into a structured JSON error response.
     * Hides sensitive details in production.
     */
    public static function exception(Throwable $e, int $code = 500): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
            'error_code' => 'INTERNAL_SERVER_ERROR',
        ];

        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($response, $code);
    }
}
