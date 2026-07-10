<?php

declare(strict_types=1);

use App\Models\User;

function selectCropTestImage(string $name, int $width, int $height): string
{
    return sprintf(<<<'JS'
        (async () => {
            const canvas = document.createElement('canvas');
            canvas.width = %d;
            canvas.height = %d;
            const context = canvas.getContext('2d');
            context.fillStyle = '#f97316';
            context.fillRect(0, 0, canvas.width, canvas.height);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
            const file = new File([blob], '%s', { type: 'image/png' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            const input = document.querySelector('[data-test="image-crop-input"]');
            input.files = dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })()
        JS, $width, $height, $name);
}

// The pest browser test server does not forward multipart file uploads, so the apply test stubs the upload XHR to
// fail client-side; that still drives the full crop pipeline (mount, selection, canvas export, modal close, upload
// initiation, and the error callback). The upload-to-storage pipeline itself is covered by feature tests.
describe('avatar crop upload', function (): void {
    it('opens the crop modal for a selected image and applying closes it and starts the upload', function (): void {
        $this->actingAs(User::factory()->create());

        $page = visit('/user/profile')
            ->on()->desktop()
            ->waitForText('Profile Picture');

        $page->script(<<<'JS'
            (() => {
                const originalOpen = XMLHttpRequest.prototype.open;
                const originalSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                    this.__isLivewireUpload = String(url).includes('upload-file');
                    return originalOpen.call(this, method, url, ...rest);
                };
                XMLHttpRequest.prototype.send = function (body) {
                    if (this.__isLivewireUpload) {
                        setTimeout(() => this.dispatchEvent(new Event('error')), 0);
                        return;
                    }
                    return originalSend.call(this, body);
                };
            })()
            JS);

        $page->script(selectCropTestImage('crop-happy-path.png', 800, 400));

        $page->waitForText('Crop Image')
            ->assertScript('document.querySelector(\'dialog[data-modal="image-crop-photo"]\').open')
            ->assertScript("document.querySelector('cropper-selection').width > 1")
            ->click('@crop-apply-button')
            ->assertScript('document.querySelector(\'dialog[data-modal="image-crop-photo"]\').open', false)
            ->waitForText('The upload failed. Please try again.');
    });

    it('uploads the original file with a crop rect for animated images', function (): void {
        $this->actingAs(User::factory()->create());

        $page = visit('/user/profile')
            ->on()->desktop()
            ->waitForText('Profile Picture');

        $page->script(<<<'JS'
            (() => {
                const originalOpen = XMLHttpRequest.prototype.open;
                const originalSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                    this.__isLivewireUpload = String(url).includes('upload-file');
                    return originalOpen.call(this, method, url, ...rest);
                };
                XMLHttpRequest.prototype.send = function (body) {
                    if (this.__isLivewireUpload) {
                        setTimeout(() => this.dispatchEvent(new Event('error')), 0);
                        return;
                    }
                    return originalSend.call(this, body);
                };
            })()
            JS);

        $page->script(sprintf(<<<'JS'
            (() => {
                const bytes = Uint8Array.from(atob('%s'), (c) => c.charCodeAt(0));
                const file = new File([bytes], 'animated.gif', { type: 'image/gif' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                const input = document.querySelector('[data-test="image-crop-input"]');
                input.files = dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            })()
            JS, base64_encode(makeAnimatedTestImage(2, 400, 400))));

        $page->waitForText('Crop Image')
            ->assertScript("document.querySelector('cropper-selection').width > 1")
            ->click('@crop-apply-button')
            ->assertScript('document.querySelector(\'dialog[data-modal="image-crop-photo"]\').open', false)
            ->assertScript('window.Livewire.all().some((c) => c.$wire.photoCropRect && c.$wire.photoCropRect.width >= 128)')
            ->waitForText('The upload failed. Please try again.');
    });

    it('cancels the crop without uploading and allows re-selecting the same file', function (): void {
        $this->actingAs(User::factory()->create());

        $page = visit('/user/profile')
            ->on()->desktop()
            ->waitForText('Profile Picture');

        $page->script(selectCropTestImage('crop-cancel-path.png', 400, 400));

        $page->waitForText('Crop Image')
            ->click('@crop-cancel-button')
            ->assertScript('document.querySelector(\'dialog[data-modal="image-crop-photo"]\').open', false);

        $page->script(selectCropTestImage('crop-cancel-path.png', 400, 400));

        $page->waitForText('Crop Image')
            ->assertScript('document.querySelector(\'dialog[data-modal="image-crop-photo"]\').open')
            ->assertNoJavaScriptErrors();
    });
});
