<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Default Favourites lists are now locked to Private visibility. Earlier
     * the visibility selector was editable, so some default lists may carry a
     * Public or Hidden value. This brings every existing default list back in
     * line. The update is idempotent: rows already Private are untouched.
     */
    public function up(): void
    {
        DB::table('mod_lists')
            ->where('is_default', true)
            ->where('visibility', '!=', ListVisibility::Private->value)
            ->update(['visibility' => ListVisibility::Private->value]);
    }

    /**
     * Reverse the migrations.
     *
     * The previous visibility of each default list cannot be recovered, and
     * Private remains a valid value, so the reversal is intentionally a no-op.
     */
    public function down(): void
    {
        //
    }
};
