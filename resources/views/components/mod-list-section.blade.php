@foreach ($sections as $section)
    @include('components.mod-list-section-partial', [
        'title' => $section['title'],
        'mod.index' => $section['mod.index'],
        'versionScope' => $section['versionScope'],
    ])
@endforeach
