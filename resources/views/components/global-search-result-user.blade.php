<a
    href="/user/{{ $result['id'] }}/{{ Str::slug($result['name']) }}"
    wire:navigate
    class="{{ $linkClass }}"
>
    <p>{{ $result['name'] }}</p>
</a>
