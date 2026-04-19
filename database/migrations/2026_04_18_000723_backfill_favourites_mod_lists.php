<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $title = config()->string('mod-lists.favourites.title', 'Favourites');
        $slug = config()->string('mod-lists.favourites.slug', 'favourites');

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($users) use ($now, $title, $slug): void {
                $rows = [];
                foreach ($users as $user) {
                    $rows[] = [
                        'owner_id' => $user->id,
                        'title' => $title,
                        'slug' => $slug,
                        'description' => null,
                        'description_html' => null,
                        'visibility' => ListVisibility::Private->value,
                        'spt_version_id' => null,
                        'share_token' => null,
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('mod_lists')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('mod_lists')->where('is_default', true)->delete();
    }
};
