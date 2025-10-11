<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Override;

/**
 * Custom broadcasting controller for visitor presence channels.
 *
 * This controller extends Laravel's default broadcast authentication to allow both authenticated users and guest users
 * (identified by session ID) to participate in the "visitors" presence channel.
 */
class VisitorsPresenceBroadcastingController extends BroadcastController
{
    /**
     * Authenticate the user for broadcasting channels.
     *
     * Provides custom authentication logic for the "visitors" presence channel while falling back to default
     * authentication for other channels.
     *
     * @return Response|JsonResponse|array{id: string, name?: string, type?: string}
     */
    #[Override]
    public function authenticate(Request $request): Response|JsonResponse|array
    {
        $channelName = $request->input('channel_name');

        // Custom auth logic for the 'visitors' presence channel.
        if ($channelName === 'presence-visitors') {
            return $this->authenticateVisitorsChannel($request);
        }

        // For all other channels, use default authentication.
        return parent::authenticate($request);
    }

    /**
     * Authenticate users for the "visitors" presence channel.
     *
     * This method allows both authenticated users and guest users to join the "visitors" presence channel. For
     * unauthenticated requests, it creates a Guest user instance using a hashed version of the session ID as the
     * identifier to prevent leaking the actual session ID.
     *
     * @return Response|JsonResponse|array{id: string, name?: string, type?: string}
     */
    protected function authenticateVisitorsChannel(Request $request): Response|JsonResponse|array
    {
        $user = $request->user();

        // If this isn't an authenticated user, we will create a "guest" user and bind it to the request
        if (! $user) {
            $sessionId = $request->session()->getId();
            if (! $sessionId) {
                return response('No session available', 403);
            }

            // Hash the session ID to prevent leaking the actual session ID
            // Using a deterministic hash so the same session always gets the same ID
            $maskedSessionId = mb_substr(hash('sha256', $sessionId.config('app.key')), 0, 16);
            $guestUser = new Guest($maskedSessionId);

            // Set this as the user for the request
            $request->setUserResolver(fn (): Guest => $guestUser);
        }

        // Let Laravel's parent method handle everything else
        return parent::authenticate($request);
    }
}
