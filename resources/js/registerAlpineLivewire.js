import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

Alpine.plugin(focus);
Alpine.plugin(collapse);

// Register Alpine components before Livewire starts
import './visitorTracker';

Livewire.start();
