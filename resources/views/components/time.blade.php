@props(['datetime'])

<time datetime="{{ $datetime->format('c') }}">
    {{ Carbon::dynamicFormat($datetime) }}
</time>
