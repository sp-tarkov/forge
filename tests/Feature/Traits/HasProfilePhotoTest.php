<?php

declare(strict_types=1);

use App\Models\User;

describe('defaultProfilePhotoUrl', function (): void {
    it('handles null name without throwing an error', function (): void {
        $user = User::factory()->make(['name' => null, 'profile_photo_path' => null]);

        $url = $user->profile_photo_url;

        expect($url)->toBeString()
            ->and($url)->toStartWith('https://ui-avatars.com/api/?name=');
    });

    it('handles empty string name', function (): void {
        $user = User::factory()->make(['name' => '', 'profile_photo_path' => null]);

        $url = $user->profile_photo_url;

        expect($url)->toBeString()
            ->and($url)->toStartWith('https://ui-avatars.com/api/?name=');
    });

    it('generates initials from a single word name', function (): void {
        $user = User::factory()->make(['name' => 'John', 'profile_photo_path' => null]);

        $url = $user->profile_photo_url;

        expect($url)->toContain('name=J');
    });

    it('generates initials from a multi-word name', function (): void {
        $user = User::factory()->make(['name' => 'John Doe', 'profile_photo_path' => null]);

        $url = $user->profile_photo_url;

        expect($url)->toContain('name=J+D');
    });
});
