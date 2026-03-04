<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StartConversationController extends Controller
{
    /**
     * Start or resume a conversation with the specified user.
     */
    public function __invoke(User $user): RedirectResponse
    {
        $this->authorize('initiateChat', $user);

        $currentUser = Auth::user();

        // Find or create the conversation
        $conversation = Conversation::findOrCreateBetween($currentUser, $user, creator: $currentUser);

        // If the conversation is archived for the current user, unarchive it
        if ($conversation->isArchivedBy($currentUser)) {
            if ($currentUser->can('unarchive', $conversation)) {
                $conversation->unarchiveFor($currentUser);
            }
        }

        return redirect()->to($conversation->url);
    }
}
