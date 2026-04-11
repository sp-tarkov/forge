<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Commentable;
use App\Models\CommentSubscription;
use App\Models\User;
use App\Support\ModelsThat;
use App\Traits\HasComments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

final class CommentSubscriptionController extends Controller
{
    /**
     * Handle unsubscribe request from email.
     */
    public function unsubscribe(User $user, string $commentable_type, string $commentable_id): Response
    {
        abort_unless(ModelsThat::useTrait(HasComments::class)->contains($commentable_type), 404, 'Unknown commentable type.');

        /** @var Model&Commentable<Model> $commentable */
        $commentable = $commentable_type::findOrFail($commentable_id);

        CommentSubscription::unsubscribe($user, $commentable);

        return response()->view('static.comment-unsubscribed', [
            'commentable' => $commentable,
            'user' => $user,
        ]);
    }
}
