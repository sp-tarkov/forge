<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class ModVersion extends Resource
{
    public static $model = \App\Models\ModVersion::class;

    public static $title = 'id';

    public static $search = [
        'id', 'version', 'description', 'virus_total_link',
    ];

    public static function label(): string
    {
        return 'Mod Versions';
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Version')
                ->sortable()
                ->rules('required'),

            Text::make('Description')
                ->sortable()
                ->rules('required'),

            Text::make('Virus Total Link')
                ->sortable()
                ->rules('required'),

            Number::make('Downloads')
                ->sortable()
                ->rules('required', 'integer'),

            BelongsTo::make('Mod', 'mod', Mod::class),

            BelongsTo::make('SptVersion', 'spt_version', SptVersion::class),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }
}
