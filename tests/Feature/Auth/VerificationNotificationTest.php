<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendEmailVerificationEmail;
use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Queue::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        Queue::assertPushed(SendEmailVerificationEmail::class, function (SendEmailVerificationEmail $job) use ($user): bool {
            return $job->name === $user->name
                && $job->email === $user->email;
        });
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard', absolute: false));

        Queue::assertNothingPushed();
    }

    public function test_verification_email_uses_branded_template(): void
    {
        $verificationUrl = 'https://pixel-perfect.test/email/verify/1';
        $mail = new EmailVerificationMail(
            name: 'María López',
            verificationUrl: $verificationUrl,
        );

        $mail->assertSeeInHtml('PIXEL');
        $mail->assertSeeInHtml('PERFECT');
        $mail->assertSeeInHtml('Hola, María López');
        $mail->assertSeeInHtml('Confirmar mi correo');
        $mail->assertSeeInHtml($verificationUrl);
    }
}
