<div>
    @if ($disabled)
        <div class="ribbon red z-10">{{ __('Disabled') }}</div>
    @elseif ($publishedAt == null || $publishedAt > now())
        <div class="ribbon gray z-10">{{ __('Unpublished') }}</div>
    @elseif ($featured && empty($homepageFeatured))
        <div class="ribbon z-10">{{ __('Featured!') }}</div>
    @endif
</div>
