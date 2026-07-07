<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateThumbnailVariants;
use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Queue thumbnail variant generation for mods and addons that are missing variants')]
#[Signature('thumbnails:backfill-variants {--force : Regenerate variants even when they already exist}')]
final class BackfillThumbnailVariantsCommand extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        foreach ([Mod::class, Addon::class] as $modelClass) {
            $query = $modelClass::query()
                ->withoutGlobalScopes()
                ->where('thumbnail', '!=', '')
                ->whereNotNull('thumbnail');

            if (! $this->option('force')) {
                $query->whereNull('thumbnail_variants');
            }

            foreach ($query->cursor() as $model) {
                dispatch(new GenerateThumbnailVariants($model));
                $dispatched++;
            }
        }

        $this->info(sprintf('Dispatched %d thumbnail variant generation jobs to the queue.', $dispatched));

        return self::SUCCESS;
    }
}
