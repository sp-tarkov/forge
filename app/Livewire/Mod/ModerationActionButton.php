<?php

namespace App\Livewire\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class ModerationActionButton extends Component
{
    public ?string $moderatedObjectId = null;

    public string $guid = '';

    public string $actionType;

    public string $targetType = '';

    public bool $allowActions = false;

    public bool $isRunning = false;

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount(): void
    {
        $this->guid = uniqid('', true);
    }

    public function render()
    {
        $this->allowActions = ! $this->isRunning;

        return view('livewire.mod.moderation-action-button');
    }

    public function runActionEvent(): void
    {
        $this->isRunning = true;
        $this->dispatch("startAction.{$this->guid}");
    }

    #[On('startAction.{guid}')]
    public function invokeAction(): void
    {
        if ($this->moderatedObjectId == null || $this->moderatedObjectId == '') {
            Log::info('Failed: no ID specified.');

            return;
        }

        Log::info("Object ID: $this->moderatedObjectId");

        if ($this->targetType !== 'mod' && $this->targetType !== 'modVersion') {
            Log::info('Failed: invalid target type.');

            return;
        }

        switch ($this->targetType) {
            case 'mod':
                $moderatedObject = Mod::where('id', '=', $this->moderatedObjectId)->first();
                break;

            case 'modVersion':
                $moderatedObject = ModVersion::where('id', '=', $this->moderatedObjectId)->first();
                break;

            default:
                Log::info('Failed: invalid target type.');

                return;
        }

        if ($moderatedObject == null) {
            Log::info('Failed: moderated object is null');

            return;
        }

        switch ($this->actionType) {
            case 'delete':

                $moderatedObject->delete();
                break;

            case 'enable':
            case 'disable':

                $moderatedObject->toggleDisabled();
                break;

            default:
                Log::info('Failed: invalid action type.');

                return;
        }

        $this->js('window.location.reload()');
    }
}
