<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set up the alt-detection schema: the alt_investigation_runs table that backs each queued analysis, and the
 * reverse-lookup indexes on tracking_events(ip, visitor_id) and comments(user_ip) used to correlate accounts by IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alt_investigation_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('status')->default('pending');
            $table->jsonb('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'alt_investigation_runs_user_lookup_index');
            $table->index('status');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->index(['ip', 'visitor_id'], 'tracking_events_ip_visitor_id_index');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->index('user_ip', 'comments_user_ip_index');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_user_ip_index');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->dropIndex('tracking_events_ip_visitor_id_index');
        });

        Schema::dropIfExists('alt_investigation_runs');
    }
};
