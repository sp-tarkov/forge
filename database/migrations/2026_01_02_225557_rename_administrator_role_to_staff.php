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
        UserRole::query()
            ->where('name', 'Administrator')
            ->update([
                'name' => 'Staff',
                'short_name' => 'Staff',
                'description' => 'A staff member has full access to the site.',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        UserRole::query()
            ->where('name', 'Staff')
            ->update([
                'name' => 'Administrator',
                'short_name' => 'Admin',
                'description' => 'An administrator has full access to the site.',
            ]);
    }
};
