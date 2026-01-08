<?php

declare(strict_types=1);

use App\Models\UserRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename existing Moderator role to Senior Moderator
        // All users with this role will automatically become Senior Moderators
        UserRole::query()
            ->where('name', 'Moderator')
            ->update([
                'name' => 'Senior Moderator',
                'short_name' => 'Sr. Mod',
                'description' => 'A senior moderator can moderate content and ban users.',
                'icon' => 'shield-exclamation',
            ]);

        // Create new Moderator role
        UserRole::query()->create([
            'name' => 'Moderator',
            'short_name' => 'Mod',
            'description' => 'A moderator can moderate user content.',
            'color_class' => 'orange',
            'icon' => 'wrench',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the new Moderator role
        UserRole::query()
            ->where('name', 'Moderator')
            ->delete();

        // Rename Senior Moderator back to Moderator
        UserRole::query()
            ->where('name', 'Senior Moderator')
            ->update([
                'name' => 'Moderator',
                'short_name' => 'Mod',
                'description' => 'A moderator has the ability to moderate user content.',
                'color_class' => 'orange',
                'icon' => 'wrench',
            ]);
    }
};
