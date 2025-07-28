<div class="flex align-top {{ !$this->canReport ? ' hidden' : '' }}">
    @if ($this->canReport)
        @if (get_class($reportable) === 'App\Models\User')
            <flux:button wire:click="$set('showFormModal', true)" variant="outline" size="sm" icon="flag">
                {{ __('Report') }}
            </flux:button>
        @elseif (get_class($reportable) === 'App\Models\Mod')
            <button type="button" wire:click="$set('showFormModal', true)" class="underline cursor-pointer text-sm text-slate-400">
                {{ __('Report Mod') }}
            </button>
        @elseif (get_class($reportable) === 'App\Models\Comment')
            <button type="button" wire:click="$set('showFormModal', true)" class="hover:underline cursor-pointer text-xs text-slate-400">
                {{ __('Report') }}
            </button>
        @endif

        {{-- Report Form Modal --}}
        <flux:modal wire:model.self="showFormModal" class="md:w-96 text-left">
            <div class="space-y-6">
                <div class="text-left">
                    <flux:heading size="lg" class="text-left">{{ __('Report Content') }}</flux:heading>
                    <flux:text class="mt-2 text-left">{{ __('Please select a reason for reporting this content.') }}</flux:text>
                </div>

                <div class="text-left">
                    <flux:radio.group wire:model.live="reason" label="{{ __('Reason for report') }}" class="text-left">
                        @foreach (App\Enums\ReportReason::cases() as $reportReason)
                            <flux:radio value="{{ $reportReason->value }}" label="{{ $reportReason->label() }}" />
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="text-left">
                    <flux:textarea
                        wire:model.live="context"
                        label="{{ __('Additional details (optional)') }}"
                        placeholder="{{ __('Please provide any additional context that might help us review this report...') }}"
                        rows="4"
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('showFormModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>

                    <flux:button wire:click="submit" variant="primary">
                        {{ __('Submit Report') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Thank You Modal --}}
    <flux:modal wire:model.self="showThankYouModal" class="md:w-96">
        <div class="space-y-6 p-6">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 mb-4">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <flux:heading size="lg">{{ __('Thank You') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Your report has been submitted successfully. Our team will review it as soon as possible.') }}</flux:text>
            </div>

            <div class="flex justify-center">
                <flux:button wire:click="closeThankYouModal" variant="primary">{{ __('Close') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
