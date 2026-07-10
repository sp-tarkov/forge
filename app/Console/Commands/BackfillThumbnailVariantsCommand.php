<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserImageType;
use App\Jobs\GenerateThumbnailVariants;
use App\Jobs\GenerateUserImageVariants;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Queue image variant generation for mods, addons, lists, and users that are missing variants')]
#[Signature('thumbnails:backfill-variants {--force : Regenerate variants even when they already exist}')]
final class BackfillThumbnailVariantsCommand extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        foreach ([Mod::class, Addon::class, ModList::class] as $modelClass) {
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

        foreach (UserImageType::cases() as $type) {
            $query = User::query()
                ->withoutGlobalScopes()
                ->where($type->pathColumn(), '!=', '')
                ->whereNotNull($type->pathColumn());

            if (! $this->option('force')) {
                $query->whereNull($type->variantsColumn());
            }

            foreach ($query->cursor() as $user) {
                dispatch(new GenerateUserImageVariants($user, $type));
                $dispatched++;
            }
        }

        $this->info(sprintf('Dispatched %d image variant generation jobs to the queue.', $dispatched));

        return self::SUCCESS;
    }
}
