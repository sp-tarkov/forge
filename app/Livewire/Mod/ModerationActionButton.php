<?php

namespace App\Livewire\Mod;

use App\Models\ModeratedModel;
use Livewire\Component;

class ModerationActionButton extends Component
{
    public ModeratedModel $moderatedObject;

    public string $actionType;

    public bool $allowActions = false;

    public bool $isRunning = false;

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function render()
    {
        $this->allowActions = ! $this->isRunning;

        return view('livewire.mod.moderation-action-button');
    }

    public function runActionEvent(): void
    {
        $this->isRunning = true;
        $this->js('setTimeout(function() { $wire.invokeAction(); }, 500)');
    }

    public function invokeAction(): void
    {
        switch ($this->actionType) {
            case 'delete':

                $this->moderatedObject->delete();
                break;

            case 'enable':
            case 'disable':

                $this->moderatedObject->toggleDisabled();
                break;
        }

        $this->js('window.location.reload()');
    }
}
