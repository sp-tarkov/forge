<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mod_list_items', function (Blueprint $table): void {
            $table->timestamp('tombstoned_at')->nullable()->after('position');
            $table->string('tombstoned_name')->nullable()->after('tombstoned_at');
        });
    }

    public function down(): void
    {
        Schema::table('mod_list_items', function (Blueprint $table): void {
            $table->dropColumn(['tombstoned_at', 'tombstoned_name']);
        });
    }
};
