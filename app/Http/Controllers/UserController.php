<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, int $userId, string $username): View
    {
        $user = User::whereId($userId)
            ->with(['following', 'followers'])
            ->firstOrFail();

        $mods = $user->mods()
            ->unless($request->user()?->can('viewDisabled', $user), function (Builder $query) {
                $query->where('disabled', false)
                    ->whereHas('latestVersion');
            })
            ->with([
                'users',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->fragment('mods');

        abort_if($user->slug() !== $username, 404);
        abort_if($request->user()?->cannot('view', $user), 403);

        return view('user.show', ['user' => $user, 'mods' => $mods]);
    }
}
