<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\ProcessableAnimation;
use App\Support\DataTransferObjects\ImageCropRect;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

final class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id), new NotDisposableEmail],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:1024', 'dimensions:min_width=128,min_height=128', new ProcessableAnimation],
            'photoCropRect' => ['nullable', 'array:x,y,width,height', 'required_array_keys:x,y,width,height'],
            'photoCropRect.x' => ['integer', 'min:0'],
            'photoCropRect.y' => ['integer', 'min:0'],
            'photoCropRect.width' => ['integer', 'min:128'],
            'photoCropRect.height' => ['integer', 'min:128'],
            'cover' => ['nullable', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:2048'],
            'timezone' => ['required', 'string', 'in:'.implode(',', DateTimeZone::listIdentifiers())],
            'about' => ['nullable', 'string', 'max:500'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo']) && $input['photo'] instanceof UploadedFile) {
            $cropRect = isset($input['photoCropRect']) && is_array($input['photoCropRect'])
                ? ImageCropRect::fromArray($input['photoCropRect'])
                : null;

            $user->updateProfilePhoto($input['photo'], $cropRect);
        }

        if (isset($input['cover']) && $input['cover'] instanceof UploadedFile) {
            $user->updateCoverPhoto($input['cover']);
        }

        if ($input['email'] !== $user->email) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'timezone' => $input['timezone'],
                'about' => $input['about'] ?? '',
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    private function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'timezone' => $input['timezone'],
            'about' => $input['about'] ?? '',
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
