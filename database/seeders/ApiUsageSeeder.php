<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fabricates aggregated API usage data so the admin API analytics dashboard has something to render across every
 * range. It writes both the fine-grained per-minute rows that back the 24 hour view and the coarse daily rollups that
 * back the 7/30/90 day views, plus the matching top-client rows. Latency is spread across the histogram buckets per a
 * route speed profile so the avg and p95 figures look realistic rather than parked in a single bucket.
 *
 * Both tables are cleared first, so the seeder is safe to re-run and always produces a consistent picture.
 */
final class ApiUsageSeeder extends Seeder
{
    /**
     * The open v0 API routes to fabricate traffic for, each with a relative traffic weight and a latency speed profile.
     *
     * @var array<string, array{weight: int, speed: string}>
     */
    private const array ROUTES = [
        'api.v0.mods' => ['weight' => 5, 'speed' => 'medium'],
        'api.v0.mods.show' => ['weight' => 8, 'speed' => 'fast'],
        'api.v0.mods.updates' => ['weight' => 2, 'speed' => 'slow'],
        'api.v0.addons' => ['weight' => 3, 'speed' => 'medium'],
    ];

    /**
     * Relative weight per latency bucket (index 0..9) for each speed profile. The shape of these curves is what gives
     * each route a believable avg and p95 once the request count is apportioned across the buckets.
     *
     * @var array<string, list<int>>
     */
    private const array PROFILES = [
        'fast' => [2, 6, 14, 10, 5, 2, 1, 0, 0, 0],
        'medium' => [0, 1, 5, 12, 14, 8, 3, 1, 0, 0],
        'slow' => [0, 0, 1, 3, 8, 14, 10, 5, 2, 1],
    ];

    /**
     * Representative latency in milliseconds for each bucket, used to derive a latency sum consistent with where the
     * requests landed in the histogram.
     *
     * @var list<int>
     */
    private const array REP_MS = [3, 8, 18, 38, 75, 175, 375, 750, 1750, 3000];

    /**
     * The shared created_at/updated_at timestamp for every fabricated row.
     */
    private string $nowString = '';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->nowString = $now->format('Y-m-d H:i:s');

        DB::table('api_usage_metrics')->delete();
        DB::table('api_usage_clients')->delete();

        $this->seedMinuteMetrics($now);
        $this->seedDailyMetrics($now);
        $this->seedMinuteClients($now);
        $this->seedDailyClients($now);

        $this->command->info('Seeded API usage metrics and clients for the analytics dashboard.');
    }

    /**
     * Fine-grained per-minute rows across the last 24 hours, bucketed every 10 minutes with a diurnal traffic curve so
     * the volume chart has a believable daily shape. These back the dashboard's 24 hour range.
     */
    private function seedMinuteMetrics(CarbonImmutable $now): void
    {
        $base = $now->subDay()->startOfMinute();
        $rows = [];

        foreach (range(1, 144) as $step) {
            $bucket = $base->addMinutes($step * 10);
            $factor = $this->diurnalFactor($bucket);

            foreach (self::ROUTES as $route => $meta) {
                $count = max(1, (int) round($meta['weight'] * $factor * 6 * $this->jitter()));
                $rows[] = $this->metricRow('minute', $bucket, $route, 200, $count, $meta['speed']);

                if (random_int(1, 100) <= 25) {
                    $rows[] = $this->metricRow('minute', $bucket, $route, 404, random_int(1, max(1, intdiv($count, 8))), $meta['speed']);
                }

                if (random_int(1, 100) <= 8) {
                    $rows[] = $this->metricRow('minute', $bucket, $route, 500, random_int(1, max(1, intdiv($count, 15))), 'slow');
                }

                if (random_int(1, 100) <= 6) {
                    $rows[] = $this->metricRow('minute', $bucket, $route, 429, random_int(1, max(1, intdiv($count, 10))), 'fast');
                }
            }
        }

        $this->insertChunks('api_usage_metrics', $rows);
    }

    /**
     * Coarse daily rollup rows across the last 90 days, with a gentle upward trend toward today. These back the
     * dashboard's 7, 30, and 90 day ranges.
     */
    private function seedDailyMetrics(CarbonImmutable $now): void
    {
        $rows = [];

        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $day = $now->subDays($daysAgo)->startOfDay();
            $trend = 0.6 + 0.4 * (90 - $daysAgo) / 90;

            foreach (self::ROUTES as $route => $meta) {
                $count = max(1, (int) round($meta['weight'] * $trend * 700 * $this->jitter()));
                $rows[] = $this->metricRow('day', $day, $route, 200, $count, $meta['speed']);
                $rows[] = $this->metricRow('day', $day, $route, 404, max(1, (int) round($count * random_int(2, 6) / 100)), $meta['speed']);

                if (random_int(1, 100) <= 70) {
                    $rows[] = $this->metricRow('day', $day, $route, 500, max(1, (int) round($count * random_int(1, 3) / 100)), 'slow');
                }

                if (random_int(1, 100) <= 60) {
                    $rows[] = $this->metricRow('day', $day, $route, 429, max(1, (int) round($count * random_int(1, 4) / 100)), 'fast');
                }
            }
        }

        $this->insertChunks('api_usage_metrics', $rows);
    }

    /**
     * Per-minute top-client rows scattered across the last 24 hours. Counts are accumulated per (minute, ip) so the
     * unique dimension key is never violated when the same caller lands in the same minute twice.
     */
    private function seedMinuteClients(CarbonImmutable $now): void
    {
        $ips = $this->ipPool(40);
        $totals = [];

        foreach (range(1, 150) as $ignored) {
            $minute = $now->subMinutes(random_int(1, 1439))->startOfMinute()->format('Y-m-d H:i:s');
            $key = $minute.'|'.$ips[array_rand($ips)];
            $totals[$key] = ($totals[$key] ?? 0) + random_int(5, 600);
        }

        $this->insertChunks('api_usage_clients', $this->clientRows('minute', $totals));
    }

    /**
     * Daily top-client rows scattered across the last 90 days, accumulated per (day, ip) for the same reason.
     */
    private function seedDailyClients(CarbonImmutable $now): void
    {
        $ips = $this->ipPool(50);
        $totals = [];

        foreach (range(1, 400) as $ignored) {
            $day = $now->subDays(random_int(0, 89))->startOfDay()->format('Y-m-d H:i:s');
            $key = $day.'|'.$ips[array_rand($ips)];
            $totals[$key] = ($totals[$key] ?? 0) + random_int(20, 8000);
        }

        $this->insertChunks('api_usage_clients', $this->clientRows('day', $totals));
    }

    /**
     * Build a single metric row, spreading the request count across the latency histogram per the speed profile.
     *
     * @return array<string, int|string>
     */
    private function metricRow(string $period, CarbonImmutable $start, string $route, int $status, int $count, string $speed): array
    {
        ['histogram' => $histogram, 'latency_sum_ms' => $latencySum] = $this->distributeLatency($count, self::PROFILES[$speed]);

        return [
            'period' => $period,
            'period_start' => $start->format('Y-m-d H:i:s'),
            'route_name' => $route,
            'method' => 'GET',
            'status_code' => $status,
            'request_count' => $count,
            'latency_sum_ms' => $latencySum,
            ...$histogram,
            'created_at' => $this->nowString,
            'updated_at' => $this->nowString,
        ];
    }

    /**
     * Turn an accumulated "period_start|ip" => count map into insertable client rows.
     *
     * @param  array<string, int>  $totals
     * @return list<array<string, int|string>>
     */
    private function clientRows(string $period, array $totals): array
    {
        $rows = [];

        foreach ($totals as $key => $count) {
            [$periodStart, $ip] = explode('|', $key, 2);
            $rows[] = [
                'period' => $period,
                'period_start' => $periodStart,
                'ip' => $ip,
                'request_count' => $count,
                'created_at' => $this->nowString,
                'updated_at' => $this->nowString,
            ];
        }

        return $rows;
    }

    /**
     * Apportion a request count across the 10 latency buckets by weight, sending any rounding remainder to the heaviest
     * bucket, and return both the histogram columns and a latency sum consistent with that distribution.
     *
     * @param  list<int>  $weights
     * @return array{histogram: array<string, int>, latency_sum_ms: int}
     */
    private function distributeLatency(int $count, array $weights): array
    {
        $histogram = array_fill(0, 10, 0);
        $weightSum = array_sum($weights);

        if ($count <= 0 || $weightSum <= 0) {
            return ['histogram' => $this->histogramColumns($histogram), 'latency_sum_ms' => 0];
        }

        $assigned = 0;
        $heaviest = 0;

        foreach ($weights as $index => $weight) {
            $histogram[$index] = (int) floor($count * $weight / $weightSum);
            $assigned += $histogram[$index];

            if ($weight > $weights[$heaviest]) {
                $heaviest = $index;
            }
        }

        $histogram[$heaviest] += $count - $assigned;

        $latencySum = 0;

        foreach ($histogram as $index => $bucketCount) {
            $latencySum += $bucketCount * self::REP_MS[$index];
        }

        return ['histogram' => $this->histogramColumns($histogram), 'latency_sum_ms' => $latencySum];
    }

    /**
     * Key the histogram counts by their lat_b0..lat_b9 column names.
     *
     * @param  array<int, int>  $histogram
     * @return array<string, int>
     */
    private function histogramColumns(array $histogram): array
    {
        $columns = [];

        foreach ($histogram as $index => $count) {
            $columns['lat_b'.$index] = $count;
        }

        return $columns;
    }

    /**
     * A diurnal traffic multiplier that peaks around midday UTC and bottoms out overnight.
     */
    private function diurnalFactor(CarbonImmutable $time): float
    {
        $hour = $time->hour + $time->minute / 60;

        return 0.35 + 0.65 * sin(M_PI * $hour / 24);
    }

    /**
     * A small random multiplier in the 0.8..1.2 range to keep counts from looking mechanical.
     */
    private function jitter(): float
    {
        return random_int(80, 120) / 100;
    }

    /**
     * A pool of client IPs to draw callers from. Duplicates are harmless: client rows are accumulated per
     * (period_start, ip), so a repeated IP simply folds into the same bucket rather than colliding on the unique key.
     *
     * @return non-empty-list<string>
     */
    private function ipPool(int $size): array
    {
        $ips = [fake()->ipv4()];

        for ($i = 1; $i < $size; $i++) {
            $ips[] = fake()->ipv4();
        }

        return $ips;
    }

    /**
     * Insert rows in chunks to keep individual statements within sane bounds.
     *
     * @param  list<array<string, int|string>>  $rows
     */
    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
