<?php

declare(strict_types=1);

use App\Models\ModVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the new polymorphic table
        Schema::create('virus_total_links', function (Blueprint $table) {
            $table->id();
            $table->morphs('linkable');
            $table->string('url');
            $table->string('label')->default('');
            $table->timestamps();
        });

        // Migrate existing ModVersion VirusTotal links
        ModVersion::query()
            ->whereNotNull('virus_total_link')
            ->where('virus_total_link', '!=', '')
            ->each(function (ModVersion $modVersion) {
                $modVersion->virusTotalLinks()->create([
                    'url' => $modVersion->virus_total_link,
                    'label' => '',
                ]);
            });

        // Remove virus_total_link column from mod_versions table
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->dropColumn('virus_total_link');
        });

        // Remove virus_total_link column from addon_versions table
        Schema::table('addon_versions', function (Blueprint $table) {
            $table->dropColumn('virus_total_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->string('virus_total_link')->nullable()->after('spt_version_constraint');
        });

        Schema::table('addon_versions', function (Blueprint $table) {
            $table->string('virus_total_link')->nullable()->after('mod_version_constraint');
        });

        Schema::dropIfExists('virus_total_links');
    }
};
