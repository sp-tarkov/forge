<?php

namespace App\Helpers;

class ColorHelper
{
    public static function tagColorClasses($color): string
    {
        return match ($color) {
            'red' => 'bg-red-50 text-red-700 ring-red-600/20',
            'green' => 'bg-green-50 text-green-700 ring-green-600/20',
            'yellow' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
        };
    }
}
