<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, int $userId, string $username): View
    {
        $user = User::where('id', $userId)
            // Reimplement eager loading after the review.
            ->firstOrFail();

        if ($user->slug() !== $username) {
            abort(404);
        }

        if ($request->user()?->cannot('view', $user)) {
            abort(403);
        }

        return view('user.show', compact('user'));
    }
}
