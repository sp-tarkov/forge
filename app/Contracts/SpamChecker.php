<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Comment;
use App\Support\Akismet\SpamCheckResult;

interface SpamChecker
{
    public function verifyKey(): bool;

    public function checkSpam(Comment $comment): SpamCheckResult;

    public function markAsHam(Comment $comment): void;

    public function markAsSpam(Comment $comment): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getUsageLimit(): ?array;
}
