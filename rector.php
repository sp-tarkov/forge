<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/lang',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'*.blade.php',
        __DIR__.'/bootstrap/cache',
    ])
    ->withCache(
        cacheDirectory: '.rector/cache',
        cacheClass: FileCacheStorage::class
    )
    ->withPhpSets(php84: true)
    ->withSets([
        LaravelSetList::LARAVEL_120,
        LaravelSetList::LARAVEL_110,
        LaravelSetList::LARAVEL_100,
        LaravelSetList::LARAVEL_90,
        LaravelSetList::LARAVEL_80,
        LaravelSetList::LARAVEL_70,
        LaravelSetList::LARAVEL_60,
        LaravelSetList::ARRAY_STR_FUNCTIONS_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ])
    ->withTypeCoverageLevel(10)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withCodingStyleLevel(10)
    ->withImportNames(removeUnusedImports: true)
    ->withAttributesSets()
    ->withTypeCoverageDocblockLevel(10);
