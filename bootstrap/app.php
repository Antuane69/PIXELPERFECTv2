<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->stopIgnoring(AuthenticationException::class);
        $exceptions->stopIgnoring(AuthorizationException::class);
        $exceptions->stopIgnoring(BackedEnumCaseNotFoundException::class);
        $exceptions->stopIgnoring(HttpException::class);
        $exceptions->stopIgnoring(HttpResponseException::class);
        $exceptions->stopIgnoring(ModelNotFoundException::class);
        $exceptions->stopIgnoring(OriginMismatchException::class);
        $exceptions->stopIgnoring(RecordNotFoundException::class);
        $exceptions->stopIgnoring(RecordsNotFoundException::class);
        $exceptions->stopIgnoring(TokenMismatchException::class);
        $exceptions->stopIgnoring(ValidationException::class);
        $exceptions->dontReportDuplicates();
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if (! should_render_friendly_error($request)) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                if (! $request->is('api/*') && ! $request->expectsJson()) {
                    return null;
                }

                return response()->json(
                    [
                        ...friendly_error_payload($exception),
                        'errors' => $exception->errors(),
                    ],
                    friendly_error_status($exception),
                );
            }

            return response()->json(
                friendly_error_payload($exception),
                friendly_error_status($exception),
            );
        });
    })->create();
