@foreach ($sections as $section)
    @include('components.mod-list-section-partial', [
        'title' => $section['title'],
        'mods' => $section['mods'],
        'versionScope' => $section['versionScope'],
        'link' => $section['link']
    ])
@endforeach
