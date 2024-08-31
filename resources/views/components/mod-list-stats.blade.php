<p {{ $attributes->class(['text-slate-700 dark:text-gray-300 text-sm']) }}>
    <span title="{{ __('Exactly') }} {{ $mod->downloads }}">{{ Number::downloads($mod->downloads) }} downloads</span>
    @if(!is_null($mod->created_at))
        <span>
            &mdash; Created
            <time datetime="{{ $mod->created_at->format('c') }}">
                {{ $mod->created_at->diffForHumans() }}
            </time>
        </span>
    @elseif(!is_null($modVersion->updated_at))
        <span>&mdash; Updated {{ $modVersion->updated_at->diffForHumans() }}</span>
    @endif
</p>
