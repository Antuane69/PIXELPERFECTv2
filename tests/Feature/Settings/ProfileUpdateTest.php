<?php

namespace Tests\Feature\Settings;

use App\Jobs\SendEmailVerificationEmail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => '  Test   User  ',
                'email' => ' TEST@Example.COM ',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Queue::assertPushed(
            SendEmailVerificationEmail::class,
            fn (SendEmailVerificationEmail $job): bool => $job->email === $user->email,
        );
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
        Queue::assertNothingPushed();
    }

    public function test_profile_avatar_can_be_updated_and_is_shared_as_a_data_uri(): void
    {
        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.png', 120, 120);
        $avatarContents = file_get_contents($avatar->getPathname());

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatar,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertIsString($avatarContents);
        $this->assertIsString($user->avatar);
        $this->assertSame('image/png', $user->avatar_mime_type);

        $decodedAvatar = imagecreatefromstring($user->avatar);

        $this->assertInstanceOf(\GdImage::class, $decodedAvatar);
        $this->assertSame(120, imagesx($decodedAvatar));
        $this->assertSame(120, imagesy($decodedAvatar));
        imagedestroy($decodedAvatar);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(
                fn (Assert $page): Assert => $page->where(
                    'auth.user.avatar',
                    'data:image/png;base64,'.base64_encode($user->avatar),
                ),
            );
    }

    public function test_profile_avatar_must_be_a_supported_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->create(
                    'avatar.txt',
                    10,
                    'text/plain',
                ),
            ])
            ->assertSessionHasErrors('avatar')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_profile_avatar_up_to_five_megabytes_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('avatar.png', 120, 120)->size(5000),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));
    }

    public function test_profile_avatar_over_five_megabytes_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->image('avatar.png', 120, 120)->size(5001),
            ])
            ->assertSessionHasErrors('avatar')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_last_administrator_cannot_delete_their_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('Administrador');

        $this->actingAs($administrator)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('roles')
            ->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($administrator);
        $this->assertModelExists($administrator);
    }
}
