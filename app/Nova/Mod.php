<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;

class Mod extends Resource
{
    public static $model = \App\Models\Mod::class;

    public static $title = 'name';

    public static $search = [
        'id', 'name', 'slug', 'description', 'source_code_link',
    ];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required'),

            Text::make('Slug')
                ->sortable()
                ->rules('required'),

            Text::make('Description')
                ->sortable()
                ->rules('required'),

            Text::make('Source Code Link')
                ->sortable()
                ->rules('required'),

            Boolean::make('Contains AI Content')
                ->sortable()
                ->rules('required'),

            BelongsTo::make('User', 'user', User::class),

            BelongsTo::make('License', 'license', License::class),

            HasMany::make('Versions', 'versions', ModVersion::class),
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
