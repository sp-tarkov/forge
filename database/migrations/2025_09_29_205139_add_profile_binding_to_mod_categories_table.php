<?php

declare(strict_types=1);

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
        Schema::table('mod_categories', function (Blueprint $table) {
            $table->boolean('shows_profile_binding_notice')
                ->default(false)
                ->after('slug');
        });

        // Set default values for categories that typically bind to profiles
        DB::table('mod_categories')
            ->whereIn('id', [2, 4, 7, 13, 14, 15, 16])
            ->update(['shows_profile_binding_notice' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_categories', function (Blueprint $table) {
            $table->dropColumn('shows_profile_binding_notice');
        });
    }
};
