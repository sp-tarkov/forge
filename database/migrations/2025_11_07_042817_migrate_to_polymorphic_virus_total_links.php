<?php

declare(strict_types=1);

use App\Models\ModVersion;
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
        // Create the new polymorphic table
        Schema::create('virus_total_links', function (Blueprint $table): void {
            $table->id();
            $table->morphs('linkable');
            $table->string('url');
            $table->string('label')->default('');
            $table->timestamps();
        });

        // Migrate existing ModVersion VirusTotal links
        DB::table('mod_versions')
            ->whereNotNull('virus_total_link')
            ->where('virus_total_link', '!=', '')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('virus_total_links')->insert([
                    'linkable_type' => ModVersion::class,
                    'linkable_id' => $row->id,
                    'url' => $row->virus_total_link,
                    'label' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Remove virus_total_link column from mod_versions table
        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->dropColumn('virus_total_link');
        });

        // Remove virus_total_link column from addon_versions table
        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->dropColumn('virus_total_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->string('virus_total_link')->nullable()->after('spt_version_constraint');
        });

        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->string('virus_total_link')->nullable()->after('mod_version_constraint');
        });

        Schema::dropIfExists('virus_total_links');
    }
};
