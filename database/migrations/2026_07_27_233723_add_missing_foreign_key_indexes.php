<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const array INDEXES = [
        'addons' => ['detached_by_user_id', 'license_id'],
        'comments' => ['parent_id', 'spam_reviewed_by', 'user_id'],
        'conversations' => ['last_message_id', 'user2_id'],
        'dependencies_resolved' => ['dependency_id', 'resolved_mod_version_id'],
        'messages' => ['user_id'],
        'mod_lists' => ['forked_from_list_id', 'spt_version_id'],
        'mods' => ['license_id', 'owner_id'],
        'oauth_connections' => ['user_id'],
        'report_actions' => ['moderator_id', 'tracking_event_id'],
        'reports' => ['assignee_id'],
        'user_follows' => ['follower_id', 'following_id'],
    ];

    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                if ($driver === 'pgsql') {
                    DB::statement(sprintf(
                        'create index concurrently if not exists %s_%s_index on %s (%s)',
                        $table,
                        $column,
                        $table,
                        $column,
                    ));
                } else {
                    DB::statement(sprintf(
                        'create index if not exists %s_%s_index on %s (%s)',
                        $table,
                        $column,
                        $table,
                        $column,
                    ));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                if ($driver === 'pgsql') {
                    DB::statement(sprintf('drop index concurrently if exists %s_%s_index', $table, $column));
                } else {
                    DB::statement(sprintf('drop index if exists %s_%s_index', $table, $column));
                }
            }
        }
    }
};
