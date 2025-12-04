<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RegenerateDescriptionHtmlCommand extends Command
{
    protected $signature = 'app:regenerate-description-html
                            {--model= : Specific model to regenerate (mod, mod-version, addon, addon-version, message, comment, user)}
                            {--force : Regenerate all records, not just those with null cached HTML}';

    protected $description = 'Regenerate the cached HTML for all models with markdown content';

    /**
     * Model configurations: [modelClass => [sourceColumn, targetColumn, regenerateMethod]]
     *
     * @var array<class-string<Model>, array{string, string, string}>
     */
    private array $modelConfigs = [
        Mod::class => ['description', 'description_html', 'regenerateDescriptionHtml'],
        ModVersion::class => ['description', 'description_html', 'regenerateDescriptionHtml'],
        Addon::class => ['description', 'description_html', 'regenerateDescriptionHtml'],
        AddonVersion::class => ['description', 'description_html', 'regenerateDescriptionHtml'],
        Message::class => ['content', 'content_html', 'regenerateContentHtml'],
        Comment::class => ['body', 'body_html', 'regenerateBodyHtml'],
        User::class => ['about', 'about_html', 'regenerateAboutHtml'],
    ];

    public function handle(): int
    {
        /** @var string|null $modelOption */
        $modelOption = $this->option('model');
        /** @var bool $force */
        $force = (bool) $this->option('force');

        $models = match ($modelOption) {
            'mod' => [Mod::class],
            'mod-version' => [ModVersion::class],
            'addon' => [Addon::class],
            'addon-version' => [AddonVersion::class],
            'message' => [Message::class],
            'comment' => [Comment::class],
            'user' => [User::class],
            default => array_keys($this->modelConfigs),
        };

        foreach ($models as $modelClass) {
            $this->regenerateForModel($modelClass, $force);
        }

        $this->newLine();
        $this->info('HTML regeneration complete!');

        return self::SUCCESS;
    }

    /**
     * Regenerate cached HTML for a specific model class.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function regenerateForModel(string $modelClass, bool $force): void
    {
        $modelName = class_basename($modelClass);
        $config = $this->modelConfigs[$modelClass];
        [$sourceColumn, $targetColumn, $regenerateMethod] = $config;

        $query = $modelClass::query()
            ->withoutGlobalScopes()
            ->whereNotNull($sourceColumn)
            ->where($sourceColumn, '!=', '');

        if (! $force) {
            $query->whereNull($targetColumn);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info("No {$modelName} records to process.");

            return;
        }

        $this->info("Processing {$count} {$modelName} records...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(100, function (mixed $records) use ($bar, $regenerateMethod): void {
            /** @var Collection<int, Model> $records */
            foreach ($records as $record) {
                $record->$regenerateMethod();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
