import { Livewire } from "../../vendor/livewire/livewire/dist/livewire.esm";
import axios from "axios";

Livewire.start();

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
