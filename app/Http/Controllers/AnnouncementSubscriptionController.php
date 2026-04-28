<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

final class AnnouncementSubscriptionController extends Controller
{
    /**
     * Disable announcement email notifications for the given user.
     */
    public function unsubscribe(User $user): Response
    {
        $user->update(['email_announcement_notifications_enabled' => false]);

        return response()->view('static.announcement-unsubscribed', [
            'user' => $user,
        ]);
    }
}
