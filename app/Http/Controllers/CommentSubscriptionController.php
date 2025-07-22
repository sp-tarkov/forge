<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommentSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentSubscriptionController extends Controller
{
    /**
     * Handle unsubscribe request from email.
     */
    public function unsubscribe(Request $request): Response
    {
        $userId = $request->route('user');
        $commentableType = $request->route('commentable_type');
        $commentableId = $request->route('commentable_id');

        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired unsubscribe link.');

        $user = User::query()->findOrFail($userId);
        $commentable = $commentableType::findOrFail($commentableId);

        CommentSubscription::unsubscribe($user, $commentable);

        return response()->view('comment-unsubscribed', [
            'commentable' => $commentable,
            'user' => $user,
        ]);
    }
}
