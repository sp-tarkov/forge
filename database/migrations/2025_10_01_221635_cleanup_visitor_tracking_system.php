<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete all visitor tracking records (keep only peak records)
        DB::table('visitors')
            ->where('type', 'visitor')
            ->delete();

        // Drop columns that are no longer needed
        Schema::table('visitors', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('visitors_type_session_id_unique');
            $table->dropIndex('visitors_type_session_id_index');
            $table->dropIndex('visitors_type_last_activity_index');
            $table->dropIndex('visitors_type_index');
            $table->dropIndex('visitors_last_activity_index');

            // Drop foreign key
            $table->dropForeign('visitors_user_id_foreign');

            // Drop columns no longer needed for peak-only tracking
            $table->dropColumn('type');
            $table->dropColumn('session_id');
            $table->dropColumn('user_id');
            $table->dropColumn('last_activity');
        });

        // Rename the table to better reflect its purpose
        Schema::rename('visitors', 'visitor_peaks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back to visitors
        Schema::rename('visitor_peaks', 'visitors');

        // Re-add the columns
        Schema::table('visitors', function (Blueprint $table) {
            $table->enum('type', ['visitor', 'peak'])->after('id');
            $table->string('session_id')->after('type');
            $table->unsignedBigInteger('user_id')->nullable()->after('session_id');
            $table->timestamp('last_activity')->nullable()->after('user_id');

            // Re-add indexes
            $table->index('type');
            $table->index('last_activity');
            $table->index(['type', 'last_activity']);
            $table->index(['type', 'session_id']);
            $table->unique(['type', 'session_id']);

            // Re-add foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
