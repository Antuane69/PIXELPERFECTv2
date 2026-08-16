<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_http_errors_return_a_safe_message(): void
    {
        $technicalMessage = 'The route /testing/secret does not exist.';

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/testing/secret');

        $response
            ->assertNotFound()
            ->assertJson([
                'message' => 'No encontramos la página o el recurso solicitado.',
            ])
            ->assertJsonMissing([
                'message' => $technicalMessage,
            ]);
    }

    public function test_inertia_validation_keeps_field_errors(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patch(route('profile.update'), [
                'name' => '',
                'email' => 'correo-invalido',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_ignored_http_errors_are_logged_with_full_exception_context(): void
    {
        $logger = Log::spy();

        $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/testing/missing-resource');

        $logger->shouldHaveReceived('error', function (string $message, array $context): bool {
            return str_contains($message, 'testing/missing-resource')
                && ($context['exception'] ?? null) instanceof NotFoundHttpException;
        });
    }

    public function test_unexpected_errors_return_safe_message_and_keep_full_log_context(): void
    {
        $technicalMessage = 'SQLSTATE[HY000]: secret database details';

        Route::get('/testing/unexpected-error', static function () use ($technicalMessage): never {
            throw new \RuntimeException($technicalMessage);
        });

        $logger = Log::spy();

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/testing/unexpected-error');

        $response
            ->assertInternalServerError()
            ->assertJson([
                'message' => 'Ocurrió un error inesperado. Inténtalo de nuevo. Si el problema continúa, contacta al administrador.',
            ])
            ->assertJsonMissing([
                'message' => $technicalMessage,
            ]);

        $logger->shouldHaveReceived('error', function (string $message, array $context) use ($technicalMessage): bool {
            return $message === $technicalMessage
                && ($context['exception'] ?? null) instanceof \RuntimeException;
        });
    }
}
