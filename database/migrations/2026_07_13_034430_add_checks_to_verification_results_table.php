<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the per-check results and check-suite version produced by the versioned container output contract, then
     * clear existing rows so every result conforms to the new contract instead of carrying a half-populated shape.
     * Also reset the denormalized verification status on the version tables so no version reports a result that no
     * longer exists.
     */
    public function up(): void
    {
        Schema::table('verification_results', function (Blueprint $table): void {
            $table->json('checks')->nullable()->after('file_tree');
            $table->string('checks_version')->nullable()->after('checks');
        });

        DB::table('verification_results')->truncate();

        DB::table('mod_versions')->update(['verification_status' => null, 'last_verified_at' => null]);
        DB::table('addon_versions')->update(['verification_status' => null, 'last_verified_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_results', function (Blueprint $table): void {
            $table->dropColumn(['checks', 'checks_version']);
        });
    }
};
