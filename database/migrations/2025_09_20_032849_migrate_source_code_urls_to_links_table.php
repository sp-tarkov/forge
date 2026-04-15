<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing source_code_url values to the new table
        $mods = DB::table('mods')
            ->whereNotNull('source_code_url')
            ->where('source_code_url', '!=', '')
            ->select(['id', 'source_code_url'])
            ->get();

        foreach ($mods as $mod) {
            // Handle comma-separated URLs from Hub imports
            /** @var string $sourceCodeUrl */
            $sourceCodeUrl = $mod->source_code_url;
            $urls = array_map(trim(...), explode(',', $sourceCodeUrl));

            foreach ($urls as $url) {
                if (! empty($url)) {
                    DB::table('mod_source_code_links')->insert([
                        'mod_id' => $mod->id,
                        'url' => $url,
                        'label' => '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the first source code link back to the source_code_url column
        $links = DB::table('mod_source_code_links')
            ->orderBy('mod_id')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<string>> $modUrls */
        $modUrls = [];
        foreach ($links as $link) {
            /** @var int $modId */
            $modId = $link->mod_id;
            if (! isset($modUrls[$modId])) {
                $modUrls[$modId] = [];
            }

            /** @var string $url */
            $url = $link->url;
            $modUrls[$modId][] = $url;
        }

        foreach ($modUrls as $modId => $urls) {
            DB::table('mods')
                ->where('id', $modId)
                ->update(['source_code_url' => implode(', ', $urls)]);
        }

        // Delete all source code links
        DB::table('mod_source_code_links')->truncate();
    }
};
