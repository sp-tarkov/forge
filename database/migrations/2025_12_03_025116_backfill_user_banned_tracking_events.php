<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfills USER_BANNED and USER_UNBANNED tracking events from historical ban records.
     */
    public function up(): void
    {
        // Get all bans (including soft-deleted ones for unbans)
        $bans = DB::table('bans')
            ->where('bannable_type', User::class)
            ->get();

        $eventsToInsert = [];
        $now = now();

        foreach ($bans as $ban) {
            // Create USER_BANNED event for every ban
            $eventsToInsert[] = [
                'event_name' => TrackingEventType::USER_BANNED->value,
                'event_data' => json_encode([
                    'backfilled' => true,
                    'ban_id' => $ban->id,
                    'comment' => $ban->comment,
                    'expired_at' => $ban->expired_at,
                    'banned_by' => $ban->created_by_id,
                ]),
                'url' => null,
                'referer' => null,
                'languages' => null,
                'useragent' => null,
                'device' => null,
                'platform' => null,
                'browser' => null,
                'ip' => null,
                'visitable_type' => User::class,
                'visitable_id' => $ban->bannable_id,
                'visitor_type' => User::class,
                'visitor_id' => $ban->bannable_id,
                'country_code' => null,
                'country_name' => null,
                'region_name' => null,
                'city_name' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
                'created_at' => $ban->created_at,
                'updated_at' => $now,
            ];

            // Create USER_UNBANNED event if the ban was lifted (soft-deleted)
            if ($ban->deleted_at !== null) {
                $eventsToInsert[] = [
                    'event_name' => TrackingEventType::USER_UNBANNED->value,
                    'event_data' => json_encode([
                        'backfilled' => true,
                        'ban_id' => $ban->id,
                    ]),
                    'url' => null,
                    'referer' => null,
                    'languages' => null,
                    'useragent' => null,
                    'device' => null,
                    'platform' => null,
                    'browser' => null,
                    'ip' => null,
                    'visitable_type' => User::class,
                    'visitable_id' => $ban->bannable_id,
                    'visitor_type' => User::class,
                    'visitor_id' => $ban->bannable_id,
                    'country_code' => null,
                    'country_name' => null,
                    'region_name' => null,
                    'city_name' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'timezone' => null,
                    'created_at' => $ban->deleted_at,
                    'updated_at' => $now,
                ];
            }
        }

        // Bulk insert in chunks for better performance
        foreach (array_chunk($eventsToInsert, 100) as $chunk) {
            TrackingEvent::query()->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Removes backfilled USER_BANNED and USER_UNBANNED tracking events.
     */
    public function down(): void
    {
        TrackingEvent::query()
            ->whereIn('event_name', [
                TrackingEventType::USER_BANNED->value,
                TrackingEventType::USER_UNBANNED->value,
            ])
            ->whereJsonContains('event_data->backfilled', true)
            ->delete();
    }
};
