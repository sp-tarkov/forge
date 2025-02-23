<div>
    @if ($disabled)
        <div class="ribbon red z-10">{{ __('Disabled') }}</div>
    @elseif ($featured && !request()->routeIs('home'))
        <div class="ribbon z-10">{{ __('Featured!') }}</div>
    @endif
</div>
