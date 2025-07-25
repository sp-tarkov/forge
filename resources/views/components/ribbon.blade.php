@props(['color', 'label'])

@if(!empty($color) && !empty($label))
    <div class="ribbon {{ $color }} z-10">{{ $label }}</div>
@endif
