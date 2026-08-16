<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HelpersTest extends TestCase
{
    /**
     * @return array<string, array{0: \Throwable, 1: string, 2: int}>
     */
    public static function friendlyErrorCases(): array
    {
        return [
            'authentication' => [
                new AuthenticationException,
                'Tu sesión terminó. Inicia sesión nuevamente.',
                401,
            ],
            'authorization' => [
                new AuthorizationException,
                'No tienes permisos para realizar esta acción.',
                403,
            ],
            'missing model' => [
                (new ModelNotFoundException)->setModel(User::class),
                'No encontramos el registro solicitado.',
                404,
            ],
            'missing route' => [
                new NotFoundHttpException('Route /secret not found.'),
                'No encontramos la página o el recurso solicitado.',
                404,
            ],
            'large request' => [
                new PostTooLargeException,
                'El archivo o la solicitud es demasiado grande.',
                413,
            ],
            'rate limit' => [
                new ThrottleRequestsException,
                'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
                429,
            ],
            'database error' => [
                new HttpException(500, 'SQLSTATE[HY000]: technical database details'),
                'Ocurrió un error interno. Inténtalo de nuevo.',
                500,
            ],
        ];
    }

    #[DataProvider('friendlyErrorCases')]
    public function test_common_exceptions_return_safe_messages(
        \Throwable $exception,
        string $expectedMessage,
        int $expectedStatus,
    ): void {
        $this->assertSame($expectedMessage, friendly_error_message($exception));
        $this->assertSame($expectedStatus, friendly_error_status($exception));
        $this->assertSame(
            ['message' => $expectedMessage],
            friendly_error_payload($exception),
        );
    }

    public function test_fallback_does_not_expose_technical_exception_details(): void
    {
        $exception = new \RuntimeException('SQLSTATE[HY000]: secret database details');

        $this->assertSame(
            'Ocurrió un error inesperado. Inténtalo de nuevo. Si el problema continúa, contacta al administrador.',
            friendly_error_message($exception),
        );
        $this->assertStringNotContainsString(
            $exception->getMessage(),
            friendly_error_payload($exception)['message'],
        );
    }
}
