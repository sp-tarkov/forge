<?php

declare(strict_types=1);

use App\Enums\UserImageType;
use App\Jobs\GenerateUserImageVariants;
use App\Jobs\NormalizeUserAvatar;
use App\Models\User;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeProfileFormTestUpload(string $format, int $width = 256, int $height = 256): UploadedFile
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('teal'));
    $image->setImageFormat($format);

    $blob = $image->getImageBlob();
    $image->clear();

    return UploadedFile::fake()->createWithContent('avatar.'.$format, $blob);
}

it('redirects guests from profile to login', function (): void {
    $this->get('/user/profile')->assertRedirect('/login');
});

describe('profile information', function (): void {
    it('shows current profile information', function (): void {
        $this->actingAs($user = User::factory()->create(['about' => 'My about content']));

        $testable = Livewire::test('profile.update-profile-form');

        expect($testable->state['name'])->toEqual($user->name)
            ->and($testable->state['email'])->toEqual($user->email)
            ->and($testable->state['about'])->toEqual('My about content');
    });

    it('can update profile information', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('state', [
                'name' => 'Test Name',
                'email' => 'test@example.com',
                'timezone' => 'America/New_York',
                'about' => 'This is my *about* me content.',
            ])
            ->call('updateProfileInformation');

        expect($user->fresh())
            ->name->toEqual('Test Name')
            ->email->toEqual('test@example.com')
            ->timezone->toEqual('America/New_York')
            ->about->toEqual('This is my *about* me content.');
    });

    it('processes about content with markdown and HTML Purifier', function (): void {
        $user = User::factory()->create(['about' => 'This is **bold** text and [a link](https://example.com)']);

        expect($user->about_html)
            ->toContain('<strong>bold</strong>')
            ->toContain('<a')
            ->toContain('href="https://example.com"');
    });
});

describe('profile images', function (): void {
    beforeEach(function (): void {
        Storage::fake('public');
        Queue::fake([GenerateUserImageVariants::class, NormalizeUserAvatar::class]);
    });

    it('saves an uploaded avatar and dispatches avatar normalization', function (): void {
        $this->actingAs($user = User::factory()->create(['about' => 'Short about text.']));

        Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        expect($user->profile_photo_path)->toStartWith('profile-photos/');
        Storage::disk('public')->assertExists($user->profile_photo_path);
        Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => $job->user->is($user)
            && ! $job->cropRect instanceof ImageCropRect);
    });

    it('passes the crop rect to avatar normalization and resets it after saving', function (): void {
        $this->actingAs($user = User::factory()->create(['about' => 'Short about text.']));

        $testable = Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512))
            ->set('photoCropRect', ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 300])
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => $job->cropRect?->x === 10
            && $job->cropRect->y === 20
            && $job->cropRect->width === 300
            && $job->cropRect->height === 300);

        expect($testable->get('photoCropRect'))->toBeNull();
    });

    it('resets the crop rect when the pending photo is removed', function (): void {
        $this->actingAs(User::factory()->create());

        $testable = Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 512, 512))
            ->set('photoCropRect', ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 300])
            ->call('removePhoto');

        expect($testable->get('photoCropRect'))->toBeNull();
    });

    it('rejects an animated avatar over the frame cap', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->createWithContent('avatar.gif', makeAnimatedTestImage(121, 200, 200)))
            ->assertHasErrors('photo');
    });

    it('accepts an animated avatar within the caps', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->createWithContent('avatar.gif', makeAnimatedTestImage(3, 200, 200)))
            ->assertHasNoErrors('photo');
    });

    it('saves an uploaded cover and dispatches variant generation', function (): void {
        $this->actingAs($user = User::factory()->create(['about' => 'Short about text.']));

        Livewire::test('profile.update-profile-form')
            ->set('cover', UploadedFile::fake()->image('banner.png', 1600, 400))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        expect($user->cover_photo_path)->toStartWith('cover-photos/');
        Queue::assertPushed(fn (GenerateUserImageVariants $job): bool => $job->user->is($user)
            && $job->type === UserImageType::CoverPhoto);
    });

    it('accepts webp, gif, and avif avatar uploads', function (string $format): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', makeProfileFormTestUpload($format))
            ->assertHasNoErrors('photo');
    })->with(['webp', 'gif', 'avif']);

    it('accepts a gif cover upload', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('cover', makeProfileFormTestUpload('gif', 1600, 400))
            ->assertHasNoErrors('cover');
    });

    it('rejects an avatar smaller than the minimum dimensions', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->image('avatar.png', 64, 64))
            ->assertHasErrors('photo');
    });

    it('rejects an avatar with an unsupported mime type', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', UploadedFile::fake()->createWithContent('avatar.txt', 'plain text content'))
            ->assertHasErrors('photo');
    });

    it('rejects an avatar with a non-image format that Imagick can read', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.update-profile-form')
            ->set('photo', makeProfileFormTestUpload('tiff'))
            ->assertHasErrors('photo');
    });
});
