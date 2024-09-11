<p {{ $attributes->class(['text-slate-700 dark:text-gray-300 text-sm']) }}>
    <div class="flex items-end w-full text-sm">
    <div class="flex items-end w-full">
        <div class="flex items-center gap-1">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 8.5v9.25A3.25 3.25 0 0 1 17.75 21H6.25A3.25 3.25 0 0 1 3 17.75V8.5h18ZM7.25 15a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5ZM12 15a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm-4.75-4.5a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm4.75 0a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm4.75 0a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Zm1-7.5A3.25 3.25 0 0 1 21 6.25V7H3v-.75A3.25 3.25 0 0 1 6.25 3h11.5Z"/>
            </svg>
            <span>
                @if(!is_null($mod->updated_at))
                    <time datetime="{{ $modVersion->updated_at->format('c') }}">
                        {{ Carbon::dynamicFormat($modVersion->updated_at) }}
                    </time>
                @elseif(!is_null($mod->created_at))
                    <time datetime="{{ $modVersion->created_at->format('c') }}">
                        {{ Carbon::dynamicFormat($modVersion->created_at) }}
                    </time>
                @else
                    N/A
                @endif
            </span>
        </div>
   </div>
   <div class="flex justify-end items-center gap-1">
       <span title="{{ __('Exactly') }} {{ $mod->downloads }}">
            {{ Number::downloads($mod->downloads) }}
       </span>
       <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
           <path d="M5.25 20.5h13.498a.75.75 0 0 1 .101 1.493l-.101.007H5.25a.75.75 0 0 1-.102-1.494l.102-.006h13.498H5.25Zm6.633-18.498L12 1.995a1 1 0 0 1 .993.883l.007.117v12.59l3.294-3.293a1 1 0 0 1 1.32-.083l.094.084a1 1 0 0 1 .083 1.32l-.083.094-4.997 4.996a1 1 0 0 1-1.32.084l-.094-.083-5.004-4.997a1 1 0 0 1 1.32-1.498l.094.083L11 15.58V2.995a1 1 0 0 1 .883-.993L12 1.995l-.117.007Z"/>
       </svg>
   </div>
   </div>
</p>
