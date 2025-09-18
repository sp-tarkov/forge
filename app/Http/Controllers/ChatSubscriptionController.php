<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChatSubscriptionController extends Controller
{
    /**
     * Handle the unsubscribe request for chat notifications.
     */
    public function unsubscribe(Request $request, User $user, string $conversationHashId): RedirectResponse
    {
        // Verify the signed URL
        abort_unless($request->hasValidSignature(), 403);

        // Find the conversation by hash ID
        $conversation = Conversation::findByHashId($conversationHashId);

        if ($conversation === null) {
            return redirect()->route('home')
                ->with('error', 'Conversation not found.');
        }

        // Verify the user is part of this conversation
        if (! $conversation->hasUser($user)) {
            return redirect()->route('home')
                ->with('error', 'You are not a participant in this conversation.');
        }

        // Disable notifications for this specific conversation
        $conversation->subscriptions()->updateOrCreate(
            ['user_id' => $user->id],
            ['notifications_enabled' => false]
        );

        return redirect()->route('chat', $conversation->hash_id)
            ->with('success', 'You have been unsubscribed from notifications for this conversation.');
    }
}
