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
        // First, handle existing duplicates by appending a number to duplicate names
        $duplicates = DB::table('users')
            ->select('name', DB::raw('COUNT(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $users = DB::table('users')
                ->where('name', $duplicate->name)
                ->orderBy('id')
                ->get();

            // Skip the first user, rename subsequent ones
            foreach ($users->skip(1) as $index => $user) {
                $newName = $duplicate->name.($index + 1);

                // Ensure the new name doesn't already exist
                while (DB::table('users')->where('name', $newName)->exists()) {
                    $newName = $duplicate->name.($index + 2);
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['name' => $newName]);
            }
        }

        // Now add the unique constraint
        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
