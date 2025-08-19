import { Alpine, Livewire } from "../../vendor/livewire/livewire/dist/livewire.esm";
import focus from "@alpinejs/focus";
import collapse from '@alpinejs/collapse'

Alpine.plugin(focus);
Alpine.plugin(collapse);

Livewire.start();
