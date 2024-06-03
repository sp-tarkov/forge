<?php

namespace App\Livewire;

use App\Models\Mod;
use Illuminate\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function render(): View
    {
        $results = $this->query ? Mod::search($this->query)->get() : [];

        return view('livewire.global-search', [
            'results' => $results,
        ]);
    }
}
