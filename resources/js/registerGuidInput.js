import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Normalize a GUID input as the user types or pastes. The value is lowercased and any character that is not a lowercase
// ASCII letter, digit, hyphen, or dot is stripped.
Alpine.magic('normalizeGuid', () => (el) => {
    const clean = (value) => value.toLowerCase().replace(/[^a-z0-9.-]/g, '');
    const cleaned = clean(el.value);

    if (el.value !== cleaned) {
        // Reposition the caret
        const caret = clean(el.value.slice(0, el.selectionStart ?? el.value.length)).length;
        el.value = cleaned;
        el.setSelectionRange(caret, caret);
    }

    return cleaned;
});
