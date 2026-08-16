<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Filesystem\FileNotFoundException as FilesystemFileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException as HttpClientConnectionException;
use Illuminate\Http\Client\RequestException as HttpClientRequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (! function_exists('friendly_error_message')) {
    /**
     * Get a safe message that can be shown to an end user.
     */
    function friendly_error_message(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => 'Revisa los datos marcados e inténtalo de nuevo.',
            $exception instanceof AuthenticationException => 'Tu sesión terminó. Inicia sesión nuevamente.',
            $exception instanceof TokenMismatchException => 'Tu sesión terminó. Recarga la página e inténtalo de nuevo.',
            $exception instanceof OriginMismatchException => 'No pudimos validar la solicitud. Recarga la página e inténtalo de nuevo.',
            $exception instanceof AuthorizationException => 'No tienes permisos para realizar esta acción.',
            $exception instanceof ModelNotFoundException => 'No encontramos el registro solicitado.',
            $exception instanceof PostTooLargeException => 'El archivo o la solicitud es demasiado grande.',
            $exception instanceof ThrottleRequestsException => 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
            $exception instanceof UniqueConstraintViolationException => 'No se pudo guardar porque la información ya existe.',
            $exception instanceof QueryException,
            $exception instanceof PDOException => 'No se pudo guardar la información. Inténtalo de nuevo.',
            $exception instanceof HttpClientConnectionException => 'No pudimos conectar con un servicio externo. Inténtalo de nuevo.',
            $exception instanceof HttpClientRequestException => 'Un servicio externo no respondió correctamente. Inténtalo de nuevo.',
            $exception instanceof FilesystemFileNotFoundException => 'No encontramos el archivo solicitado.',
            $exception instanceof RequestExceptionInterface => 'La solicitud no es válida. Revisa la información e inténtalo de nuevo.',
            $exception instanceof HttpResponseException => friendly_http_error_message(
                $exception->getResponse()->getStatusCode(),
            ),
            $exception instanceof HttpExceptionInterface => friendly_http_error_message(
                $exception->getStatusCode(),
            ),
            default => 'Ocurrió un error inesperado. Inténtalo de nuevo. Si el problema continúa, contacta al administrador.',
        };
    }
}

if (! function_exists('friendly_error_status')) {
    /**
     * Get the HTTP status that belongs to an exception.
     */
    function friendly_error_status(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof TokenMismatchException => 419,
            $exception instanceof OriginMismatchException => 403,
            $exception instanceof AuthorizationException => $exception->hasStatus()
                ? $exception->status()
                : 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof PostTooLargeException => 413,
            $exception instanceof ThrottleRequestsException => 429,
            $exception instanceof UniqueConstraintViolationException => 409,
            $exception instanceof HttpClientConnectionException => 503,
            $exception instanceof HttpClientRequestException => 502,
            $exception instanceof FilesystemFileNotFoundException => 404,
            $exception instanceof RequestExceptionInterface => 400,
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}

if (! function_exists('friendly_error_payload')) {
    /**
     * Build the safe error payload sent to Inertia and JSON clients.
     *
     * @return array{message: string}
     */
    function friendly_error_payload(Throwable $exception): array
    {
        return [
            'message' => friendly_error_message($exception),
        ];
    }
}

if (! function_exists('should_render_friendly_error')) {
    /**
     * Determine whether the request is handled by the frontend error layer.
     */
    function should_render_friendly_error(Request $request): bool
    {
        return $request->is('api/*')
            || $request->expectsJson()
            || $request->header('X-Inertia') !== null;
    }
}

if (! function_exists('friendly_http_error_message')) {
    /**
     * Get a safe message for an HTTP status code.
     */
    function friendly_http_error_message(int $status): string
    {
        return match ($status) {
            400 => 'La solicitud no es válida. Revisa la información e inténtalo de nuevo.',
            401 => 'Tu sesión no es válida o terminó. Inicia sesión nuevamente.',
            403 => 'No tienes permisos para realizar esta acción.',
            404 => 'No encontramos la página o el recurso solicitado.',
            405 => 'Esta acción no está disponible para esta solicitud.',
            408 => 'La solicitud tardó demasiado. Inténtalo de nuevo.',
            409 => 'No se pudo completar porque existe un conflicto con la información actual.',
            413 => 'El archivo o la solicitud es demasiado grande.',
            415 => 'El formato enviado no es compatible.',
            419 => 'Tu sesión terminó. Recarga la página e inténtalo de nuevo.',
            422 => 'Revisa los datos enviados e inténtalo de nuevo.',
            429 => 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
            500 => 'Ocurrió un error interno. Inténtalo de nuevo.',
            502, 503, 504 => 'El servicio no está disponible temporalmente. Inténtalo más tarde.',
            default => 'No pudimos completar la solicitud. Inténtalo de nuevo.',
        };
    }
}
