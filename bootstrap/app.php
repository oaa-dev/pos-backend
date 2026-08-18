<?php

use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidApprovalPinException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Exceptions\ShiftNotOpenException;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $isApiRequest = fn (Request $request): bool => $request->expectsJson() || $request->is('api/*');

        $responder = new class
        {
            use ApiResponse;

            public function error(string $message, int $statusCode): JsonResponse
            {
                return $this->errorResponse($message, $statusCode);
            }
        };

        $exceptions->render(function (InvalidCredentialsException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (InsufficientStockException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (CreditLimitExceededException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (SaleAlreadyVoidedException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (ShiftNotOpenException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (InvalidApprovalPinException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), $e->getStatusCode());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage() ?: 'This action is unauthorized.', 403);
        });

        // Laravel's Handler::prepareException() rewrites AuthorizationException
        // into AccessDeniedHttpException before any render callback runs, so
        // the handler above never fires on its own. This catches what actually
        // arrives and keeps 403s inside the {success, message} envelope.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage() ?: 'This action is unauthorized.', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error('Resource not found.', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error('Resource not found.', 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $errors = $e->errors();
            $firstError = Arr::first($errors)[0] ?? 'The given data was invalid.';

            return response()->json([
                'success' => false,
                'message' => $firstError,
                'errors' => $errors,
            ], $e->status);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage() ?: 'Method not allowed.', 405);
        });

        $exceptions->render(function (InvalidArgumentException $e, Request $request) use ($isApiRequest, $responder) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $responder->error($e->getMessage(), 422);
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request) || config('app.debug')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        });
    })->create();
