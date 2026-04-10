<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Commentable;
use App\Models\CommentSubscription;
use App\Models\User;
use App\Support\ModelsThat;
use App\Traits\HasComments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CommentSubscriptionController extends Controller
{
    /**
     * Handle unsubscribe request from email.
     */
    public function unsubscribe(Request $request): Response
    {
        $userId = $request->route('user');
        $commentableType = (string) $request->route('commentable_type');
        $commentableId = $request->route('commentable_id');

        abort_unless((bool) $request->hasValidSignature(), 403, 'Invalid or expired unsubscribe link.');

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        abort_unless(ModelsThat::useTrait(HasComments::class)->contains($commentableType), 404, 'Unknown commentable type.');

        /** @var Model&Commentable<Model> $commentable */
        $commentable = $commentableType::findOrFail($commentableId);

        CommentSubscription::unsubscribe($user, $commentable);

        return response()->view('static.comment-unsubscribed', [
            'commentable' => $commentable,
            'user' => $user,
        ]);
    }
}
