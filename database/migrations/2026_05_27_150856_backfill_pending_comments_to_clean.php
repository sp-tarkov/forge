<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill comments orphaned in the PENDING state. The CommentObserver was guarding its inline mark-as-clean branch
     * on `isPendingSpamCheck()`, which compares the in-memory enum to PENDING. Comments created via Eloquent without an
     * explicit spam_status leave the in-memory attribute null (the DB default fills the row only on insert), so the
     * guard returned false and the rows were never updated after Akismet was disabled. Match by the orphan signature
     * (pending + never checked) and limit to the last three days so older PENDING rows with a different lineage are not
     * touched, then resolve the matches as clean so the UI stops showing the PENDING ribbon. Idempotent: rows already
     * CLEAN or SPAM are untouched, and re-running only touches rows that re-entered the orphan state.
     */
    public function up(): void
    {
        $now = now();

        DB::table('comments')
            ->where('spam_status', SpamStatus::PENDING->value)
            ->whereNull('spam_checked_at')
            ->where('created_at', '>=', $now->subDays(3))
            ->update([
                'spam_status' => SpamStatus::CLEAN->value,
                'spam_metadata' => json_encode(['reason' => 'akismet_disabled_backfill']),
                'spam_checked_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * The original PENDING rows cannot be distinguished from genuinely-clean comments after the backfill, and reverting
     * them would re-introduce the stuck-PENDING UI bug, so the reversal is intentionally a no-op.
     */
    public function down(): void
    {
        //
    }
};
