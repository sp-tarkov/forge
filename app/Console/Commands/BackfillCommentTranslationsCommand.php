<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\CommentTranslator;
use App\Jobs\BackfillCommentTranslations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Queue language detection and bulk translation for comments that are missing them')]
#[Signature('comments:backfill-translations')]
final class BackfillCommentTranslationsCommand extends Command
{
    public function handle(CommentTranslator $commentTranslator): int
    {
        if (! config()->boolean('comments.translation.enabled', false)) {
            $this->error('Comment translation is disabled. Set COMMENT_TRANSLATION_ENABLED=true to enable it.');

            return self::FAILURE;
        }

        if (! $commentTranslator->isConfigured()) {
            $this->warn('No Anthropic API key is configured. The backfill will run local language detection only and skip translation.');
        }

        dispatch(new BackfillCommentTranslations);

        $this->info('BackfillCommentTranslations has been added to the queue');

        return self::SUCCESS;
    }
}
