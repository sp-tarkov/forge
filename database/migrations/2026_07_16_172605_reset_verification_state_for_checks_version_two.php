<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delete every stored verification result and clear the denormalized verification status, last-verified
     * timestamp, and change-detection fingerprint columns on the mod and addon version tables.
     */
    public function up(): void
    {
        DB::table('verification_results')->delete();

        foreach (['mod_versions', 'addon_versions'] as $table) {
            DB::table($table)->update([
                'etag' => null,
                'last_modified_header' => null,
                'verification_status' => null,
                'last_verified_at' => null,
            ]);
        }
    }

    /**
     * The deleted results and cleared state cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
