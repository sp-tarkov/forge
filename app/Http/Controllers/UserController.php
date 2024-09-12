<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, User $user, string $username): View
    {
        if ($user->slug() !== $username) {
            abort(404);
        }

        if ($request->user()?->cannot('view', $user)) {
            abort(403);
        }

        // not sure if this is optimal. Some way to do $user->with(...) ???
        $user = User::where('id', $user->id)
            ->with(['followers', 'following'])
            ->firstOrFail();

        return view('user.show', compact('user'));
    }
}
