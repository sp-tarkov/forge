<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class ModelsThat
{
    /**
     * Retrieves a collection of classes within the application that use a specified trait.
     *
     * @param  string  $trait  The fully qualified name of the trait to search for.
     * @return Collection<int, string> A collection of fully qualified class names that use the specified trait.
     */
    public static function useTrait(string $trait): Collection
    {
        $files = File::allFiles(app_path('Models'));

        return collect($files)
            ->map(fn (SplFileInfo $file) => str($file->getPathname())
                ->after(app_path())
                ->before('.')
                ->prepend('App')
                ->replace('/', '\\')
                ->value())
            ->filter(function (string $class) use ($trait) {
                if (! class_exists($class)) {
                    return false;
                }

                $reflection = new ReflectionClass($class);

                return in_array($trait, $reflection->getTraitNames());
            })
            ->values();
    }
}
