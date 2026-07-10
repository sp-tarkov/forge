import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Detects animated images by their container metadata: multiple GIF Graphic Control Extensions, a PNG acTL chunk
// before IDAT (APNG), a WebP ANIM chunk, or an AVIF ftyp listing the avis brand. Misdetection is harmless: the
// server flattens single-frame "animations" and center-crops rect-less animated uploads.
async function isAnimatedImage(file) {
    const buffer = new Uint8Array(await file.arrayBuffer());
    const ascii = (offset, length) => String.fromCharCode(...buffer.slice(offset, offset + length));

    if (ascii(0, 3) === 'GIF') {
        let controlBlocks = 0;
        for (let i = 0; i < buffer.length - 1; i++) {
            if (buffer[i] === 0x21 && buffer[i + 1] === 0xf9 && ++controlBlocks > 1) {
                return true;
            }
        }
        return false;
    }

    if (buffer[0] === 0x89 && ascii(1, 3) === 'PNG') {
        for (let i = 8; i + 8 <= buffer.length; ) {
            const length = (buffer[i] << 24) | (buffer[i + 1] << 16) | (buffer[i + 2] << 8) | buffer[i + 3];
            const type = ascii(i + 4, 4);
            if (type === 'acTL') return true;
            if (type === 'IDAT' || type === 'IEND') return false;
            i += 12 + length;
        }
        return false;
    }

    if (ascii(0, 4) === 'RIFF' && ascii(8, 4) === 'WEBP') {
        for (let i = 12; i + 8 <= buffer.length; ) {
            if (ascii(i, 4) === 'ANIM') return true;
            const length = buffer[i + 4] | (buffer[i + 5] << 8) | (buffer[i + 6] << 16) | (buffer[i + 7] << 24);
            i += 8 + length + (length % 2);
        }
        return false;
    }

    if (ascii(4, 4) === 'ftyp') {
        const boxSize = (buffer[0] << 24) | (buffer[1] << 16) | (buffer[2] << 8) | buffer[3];
        for (let i = 8; i + 4 <= Math.min(boxSize, buffer.length); i += 4) {
            if (ascii(i, 4) === 'avis') return true;
        }
        return false;
    }

    return false;
}

// Backs the <x-image-crop-upload> Blade component. A selected or dropped image opens a modal containing a
// Cropper.js selection locked to the configured aspect ratio; applying exports the selection to a canvas and
// uploads the result to the bound Livewire property. When animation preservation is configured and the file is
// animated, applying instead uploads the original file plus the selection as a natural-pixel crop rectangle.
Alpine.data('imageCropUpload', (config) => ({
    dragging: false,
    uploading: false,
    progress: 0,
    error: null,
    objectUrl: null,
    sourceFile: null,
    sourceType: null,
    sourceName: null,
    isAnimated: false,

    // The stage lives inside the Flux modal dialog, which is a nested Alpine component, so it cannot be an x-ref.
    stage() {
        return document.querySelector(`[data-crop-stage="${config.modalName}"]`);
    },

    onSelect(event) {
        const file = event.target.files[0] ?? null;
        event.target.value = '';
        if (file) {
            this.open(file);
        }
    },

    onDrop(event) {
        this.dragging = false;
        const file = event.dataTransfer.files[0] ?? null;
        if (file) {
            this.open(file);
        }
    },

    async open(file) {
        this.cleanup();
        this.error = null;

        if (!config.accept.includes(file.type)) {
            this.error = config.messages.type;
            return;
        }

        this.sourceFile = file;
        this.sourceType = file.type;
        this.sourceName = file.name;
        this.isAnimated = config.preserveAnimation && config.cropModel ? await isAnimatedImage(file) : false;
        this.objectUrl = URL.createObjectURL(file);

        await import('cropperjs');
        this.$flux.modal(config.modalName).show();
        await this.waitForStageLayout();
        this.mountCropper();
        await this.positionSelection();
    },

    // Resolves once the modal open animation has settled and the stage reports a stable, non-zero size across
    // three consecutive frames. Mounting or measuring the cropper before this point computes the image transform
    // against mid-animation geometry.
    async waitForStageLayout() {
        const stage = this.stage();
        let previous = null;
        let stableFrames = 0;

        for (let attempt = 0; attempt < 120; attempt++) {
            await new Promise((resolve) => requestAnimationFrame(resolve));

            const rect = stage.getBoundingClientRect();
            const stable =
                previous !== null &&
                rect.width >= 1 &&
                Math.abs(rect.width - previous.width) < 0.1 &&
                Math.abs(rect.height - previous.height) < 0.1 &&
                Math.abs(rect.left - previous.left) < 0.1 &&
                Math.abs(rect.top - previous.top) < 0.1;

            stableFrames = stable ? stableFrames + 1 : 0;
            if (stableFrames >= 3) {
                return;
            }

            previous = rect;
        }
    },

    // Scales the image to fit the canvas and centers it. The transform methods are gated behind the translatable
    // and scalable flags, which are enabled only for the duration of the call since the image must stay static.
    recenterImage(image) {
        const { translatable, scalable } = image;
        image.translatable = true;
        image.scalable = true;
        image.$center('contain');
        image.translatable = translatable;
        image.scalable = scalable;
    },

    mountCropper() {
        const fragment = this.$refs.template.content.cloneNode(true);
        fragment.querySelector('cropper-image').setAttribute('src', this.objectUrl);
        fragment
            .querySelector('cropper-selection')
            .addEventListener('change', (event) => this.constrainSelection(event));
        this.stage().replaceChildren(fragment);
    },

    // Re-centers the loaded image, then centers the selection on the largest region of the image that matches the
    // aspect ratio.
    async positionSelection() {
        const image = this.stage().querySelector('cropper-image');
        await image.$ready();
        await this.waitForStageLayout();

        const canvas = this.stage().querySelector('cropper-canvas');
        const selection = this.stage().querySelector('cropper-selection');
        if (!canvas || !selection) {
            return;
        }

        this.recenterImage(image);
        await new Promise((resolve) => requestAnimationFrame(resolve));

        const canvasRect = canvas.getBoundingClientRect();
        const imageRect = image.getBoundingClientRect();
        if (canvasRect.width < 1 || imageRect.width < 1 || imageRect.height < 1) {
            return;
        }

        const width = Math.min(imageRect.width, imageRect.height * config.aspectRatio);
        const height = width / config.aspectRatio;

        selection.$change(
            imageRect.left - canvasRect.left + (imageRect.width - width) / 2,
            imageRect.top - canvasRect.top + (imageRect.height - height) / 2,
            width,
            height,
        );
    },

    // Reject selection changes that would leave the image bounds.
    constrainSelection(event) {
        const canvas = this.stage().querySelector('cropper-canvas');
        const image = this.stage().querySelector('cropper-image');
        if (!canvas || !image) {
            return;
        }

        const canvasRect = canvas.getBoundingClientRect();
        const imageRect = image.getBoundingClientRect();
        const { x, y, width, height } = event.detail;
        const minX = imageRect.left - canvasRect.left;
        const minY = imageRect.top - canvasRect.top;
        const tolerance = 0.5;

        if (
            x < minX - tolerance ||
            y < minY - tolerance ||
            x + width > minX + imageRect.width + tolerance ||
            y + height > minY + imageRect.height + tolerance
        ) {
            event.preventDefault();
        }
    },

    async apply() {
        const selection = this.stage().querySelector('cropper-selection');
        const image = this.stage().querySelector('cropper-image');
        if (!selection || !image) {
            return;
        }

        await image.$ready();
        if (selection.width < 1 || selection.height < 1) {
            await this.positionSelection();
        }

        if (selection.width < 1 || selection.height < 1) {
            this.error = config.messages.export;
            return;
        }

        if (this.isAnimated) {
            this.applyCropRect(selection, image);
            return;
        }

        if (config.cropModel) {
            this.$wire.set(config.cropModel, null, false);
        }

        const scale = image.$image.naturalWidth / image.getBoundingClientRect().width;
        const width = Math.min(config.maxDimension, Math.round(selection.width * scale));
        const height = Math.round(width / config.aspectRatio);

        const canvas = await selection.$toCanvas({ width, height });
        let type = this.sourceType === 'image/jpeg' ? 'image/jpeg' : 'image/png';
        let blob = await this.canvasToBlob(canvas, type, 0.9);

        if (blob && type === 'image/png' && blob.size > config.maxBytes) {
            type = 'image/jpeg';
            blob = await this.canvasToBlob(canvas, type, 0.85);
        }

        if (!blob) {
            this.error = config.messages.export;
            return;
        }

        const basename = this.sourceName.replace(/\.[^.]*$/, '');
        const file = new File([blob], basename + (type === 'image/jpeg' ? '.jpg' : '.png'), { type });

        this.$flux.modal(config.modalName).close();
        this.cleanup();
        this.upload(file);
    },

    // Converts the selection into a natural-pixel crop rectangle, hands it to the bound Livewire property, and
    // uploads the original file untouched so the server can crop every animation frame.
    applyCropRect(selection, image) {
        const selectionRect = selection.getBoundingClientRect();
        const imageRect = image.getBoundingClientRect();
        const naturalWidth = image.$image.naturalWidth;
        const naturalHeight = image.$image.naturalHeight;
        const scale = naturalWidth / imageRect.width;

        const x = Math.max(0, Math.min(Math.round((selectionRect.left - imageRect.left) * scale), naturalWidth - 1));
        const y = Math.max(0, Math.min(Math.round((selectionRect.top - imageRect.top) * scale), naturalHeight - 1));
        const width = Math.min(Math.round(selectionRect.width * scale), naturalWidth - x);
        const height = Math.min(Math.round(selectionRect.height * scale), naturalHeight - y);

        if (Math.min(width, height) < config.minCropSize) {
            this.error = config.messages.selection;
            return;
        }

        this.$wire.set(config.cropModel, { x, y, width, height }, false);

        const file = this.sourceFile;
        this.$flux.modal(config.modalName).close();
        this.cleanup();
        this.upload(file);
    },

    canvasToBlob(canvas, type, quality) {
        return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    },

    upload(file) {
        this.uploading = true;
        this.progress = 0;

        this.$wire.upload(
            config.model,
            file,
            () => {
                this.uploading = false;
            },
            () => {
                this.uploading = false;
                this.error = config.messages.upload;
            },
            (event) => {
                this.progress = event.detail.progress;
            },
        );
    },

    cancel() {
        this.$flux.modal(config.modalName).close();
        this.cleanup();
    },

    cleanup() {
        this.stage().replaceChildren();
        this.sourceFile = null;
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    },
}));
