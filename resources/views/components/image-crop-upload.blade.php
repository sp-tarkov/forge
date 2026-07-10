@props([
    'wireModel',
    'id' => null,
    'aspectRatio' => 1,
    'maxDimension' => 768,
    'maxKilobytes' => 2048,
    'accept' => 'image/jpeg,image/png,image/webp,image/gif,image/avif',
    'heading' => null,
    'text' => null,
    'preserveAnimation' => false,
    'cropModel' => null,
    'minCropSize' => 128,
])

<div
    x-data="imageCropUpload({
        model: @js($wireModel),
        modalName: @js($id ?? 'image-crop-' . $wireModel),
        aspectRatio: @js($aspectRatio),
        maxDimension: @js($maxDimension),
        maxBytes: @js($maxKilobytes * 1024),
        accept: @js(explode(',', $accept)),
        preserveAnimation: @js((bool) $preserveAnimation),
        cropModel: @js($cropModel),
        minCropSize: @js($minCropSize),
        messages: {
            type: @js(__('That file type is not supported.')),
            export: @js(__('The image could not be cropped. Please try another image.')),
            upload: @js(__('The upload failed. Please try again.')),
            selection: @js(__('Selection is too small. Choose an area of at least :min x :min pixels.', ['min' => $minCropSize])),
        },
    })"
    {{ $attributes }}
>
    <label
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="onDrop"
        x-bind:data-dragging="dragging ? '' : null"
        class="border-1 data-dragging:border-white/20 data-dragging:bg-white/15 flex h-full w-full items-center rounded-lg border-dashed border-white/10 bg-white/10 p-4 pe-6 ps-4 transition-colors focus-within:border-white/30 sm:pe-8 sm:ps-5"
    >
        <input
            type="file"
            class="sr-only"
            accept="{{ $accept }}"
            data-test="image-crop-input"
            x-ref="input"
            x-on:change="onSelect"
        />
        <flux:icon
            name="cloud-arrow-up"
            variant="solid"
            class="me-4 shrink-0 text-white/60"
        />
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="text-sm font-medium text-white">
                {{ $heading ?? __('Drop image here or click to browse') }}
            </div>
            <div class="relative text-xs text-white/60">
                <div
                    x-cloak
                    x-show="uploading"
                    class="absolute inset-x-0 top-0 flex items-center gap-3"
                >
                    <div class="h-1 flex-1 rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full bg-white"
                            x-bind:style="`width: ${progress}%`"
                        ></div>
                    </div>
                    <div
                        class="font-medium tabular-nums text-white/70"
                        x-text="`${progress}%`"
                    ></div>
                </div>
                <span x-bind:class="uploading && 'opacity-0'">{{ $text }}</span>
            </div>
        </div>
    </label>

    <p
        x-cloak
        x-show="error"
        x-text="error"
        class="mt-2 text-sm text-red-400"
    ></p>

    <template x-ref="template">
        <cropper-canvas
            background
            class="h-80 w-full"
        >
            <cropper-image alt="{{ __('Image to crop') }}"></cropper-image>
            <cropper-shade hidden></cropper-shade>
            <cropper-handle
                action="select"
                plain
            ></cropper-handle>
            <cropper-selection
                initial-coverage="1"
                aspect-ratio="{{ $aspectRatio }}"
                movable
                resizable
                keyboard
                outlined
            >
                <cropper-grid
                    role="grid"
                    covered
                ></cropper-grid>
                <cropper-crosshair centered></cropper-crosshair>
                <cropper-handle
                    action="move"
                    theme-color="rgba(255, 255, 255, 0.35)"
                ></cropper-handle>
                <cropper-handle action="n-resize"></cropper-handle>
                <cropper-handle action="e-resize"></cropper-handle>
                <cropper-handle action="s-resize"></cropper-handle>
                <cropper-handle action="w-resize"></cropper-handle>
                <cropper-handle action="ne-resize"></cropper-handle>
                <cropper-handle action="nw-resize"></cropper-handle>
                <cropper-handle action="se-resize"></cropper-handle>
                <cropper-handle action="sw-resize"></cropper-handle>
            </cropper-selection>
        </cropper-canvas>
    </template>

    <flux:modal
        name="{{ $id ?? 'image-crop-' . $wireModel }}"
        class="md:w-[560px]"
        x-on:close="cleanup"
    >
        <flux:heading size="lg">{{ __('Crop Image') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Drag to move or resize the selection, then apply the crop.') }}
        </flux:text>
        <div
            wire:ignore
            data-crop-stage="{{ $id ?? 'image-crop-' . $wireModel }}"
            class="mt-4 w-full overflow-hidden rounded-lg"
        ></div>
        <p
            x-cloak
            x-show="error"
            x-text="error"
            class="mt-3 text-sm text-red-400"
        ></p>
        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                type="button"
                variant="ghost"
                data-test="crop-cancel-button"
                x-on:click="cancel"
            >
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                type="button"
                variant="primary"
                data-test="crop-apply-button"
                x-on:click="apply"
            >
                {{ __('Apply') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
