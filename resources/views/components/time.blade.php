@props(['datetime'])

<time datetime="{{ $datetime->format('c') }}" title="{{ $datetime->format('l jS \\of F Y g:i:s A e') }}">
    {{ Carbon::dynamicFormat($datetime) }}
</time>
