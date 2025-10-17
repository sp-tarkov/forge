<?php

declare(strict_types=1);

use App\Models\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->string('icon')->default('')->after('color_class');
        });

        // Update existing roles with icons
        UserRole::query()->where('name', 'Administrator')->update(['icon' => 'shield-check']);
        UserRole::query()->where('name', 'Moderator')->update(['icon' => 'wrench']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
