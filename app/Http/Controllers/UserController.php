<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function show(Request $request, int $userId, string $username): View
    {
        $user = User::whereId($userId)
            ->with(['following', 'followers'])
            ->firstOrFail();

        abort_if($user->slug !== $username, 404);
        abort_if($request->user()?->cannot('view', $user), 403);

        return view('user.show', ['user' => $user]);
    }
}
