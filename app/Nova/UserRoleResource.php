<?php

namespace App\Nova;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;

class UserRoleResource extends Resource
{
    public static string $model = UserRole::class;

    public static $title = 'name';

    public static $search = [
        'id', 'name', 'description',
    ];

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required'),

            Text::make('Description')
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
