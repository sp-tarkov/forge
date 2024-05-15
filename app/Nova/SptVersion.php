<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;

class SptVersion extends Resource
{
    public static $model = \App\Models\SptVersion::class;

    public static $title = 'id';

    public static $search = [
        'id', 'version', 'color_class',
    ];

    public static function label(): string
    {
        return 'SPT Versions';
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Version')
                ->sortable()
                ->rules('required'),

            Text::make('Color Class')
                ->sortable()
                ->rules('required'),
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
