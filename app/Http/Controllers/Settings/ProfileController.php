<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\EnsureAdministratorRemains;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\ImageCompressor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly EnsureAdministratorRemains $ensureAdministratorRemains,
        private readonly ImageCompressor $imageCompressor,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $avatar = $request->file('avatar');

        unset($data['avatar']);

        $request->user()->fill($data);

        if ($avatar !== null) {
            $compressedAvatar = $this->imageCompressor->compressIfImage($avatar);

            if ($compressedAvatar === null) {
                throw new RuntimeException('No se pudo comprimir el avatar.');
            }

            $request->user()->forceFill([
                'avatar' => $compressedAvatar['contents'],
                'avatar_mime_type' => $compressedAvatar['mime_type'],
            ]);
        }

        $emailWasChanged = $request->user()->isDirty('email');

        if ($emailWasChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailWasChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $this->ensureAdministratorRemains->handle($user);
            Auth::logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
