<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // $middleware->statefulApi();

        // Laravel 11+ no longer throttles the api group by default.
        // Limiter defined in App\Providers\AppServiceProvider.
        $middleware->throttleApi('api');

        // Route-level authorization aliases.
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->renderable(function (BusinessRuleException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->errorCode);
        });

        $exceptions->renderable(function (ValidationException $e) {
            return ApiResponse::validationError(
                $e->errors(),
                $e->getMessage()
            );
        });

        $exceptions->renderable(function (ModelNotFoundException $e) {
            $model = class_basename($e->getModel());
            return ApiResponse::notFound("{$model} not found.");
        });

        $exceptions->renderable(function (AuthenticationException $e) {
            return ApiResponse::unauthorized($e->getMessage() ?: 'Unauthenticated.');
        });

        $exceptions->renderable(function (NotFoundHttpException $e) {
            return ApiResponse::notFound('The requested endpoint does not exist.');
        });

    })->create();
