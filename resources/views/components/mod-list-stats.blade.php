<p {{ $attributes->class(['text-slate-700 dark:text-gray-300 text-sm']) }}>
    <span title="{{ __('Exactly') }} {{ $mod->downloads }}">{{ Number::downloads($mod->downloads) }} downloads</span>
    @if(!is_null($mod->created_at))
        <span>
            &mdash; Created
            <time datetime="{{ $mod->created_at->format('c') }}">
                {{ Carbon::dynamicFormat($mod->created_at) }}
            </time>
        </span>
    @elseif(!is_null($modVersion->updated_at))
        <span>
            &mdash; Updated
            <time datetime="{{ $modVersion->updated_at->format('c') }}">
                {{ Carbon::dynamicFormat($modVersion->updated_at) }}
            </time>
        </span>
    @endif
</p>
