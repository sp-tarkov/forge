<div>
    @if ($disabled)
        <div class="ribbon red z-10">{{ __('Disabled') }}</div>
    @elseif ($publishedAt === null)
        <div class="ribbon amber z-10">{{ __('Unpublished') }}</div>
    @elseif ($publishedAt > now())
        <div class="ribbon emerald z-10">{{ __('Scheduled') }}</div>
    @elseif ($featured && empty($homepageFeatured))
        <div class="ribbon sky z-10">{{ __('Featured!') }}</div>
    @endif
</div>
