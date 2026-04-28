<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# @lang('Whoops!')
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="in_array($level, ['success', 'error'], true) ? $level : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Subcopy --}}
@if (isset($actionText) || ! empty($footerLines ?? []))
<x-slot:subcopy>
@isset($actionText)
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>

@endisset
@foreach ($footerLines ?? [] as $footerLine)
{!! $footerLine !!}

@endforeach
</x-slot:subcopy>
@endif
</x-mail::message>
