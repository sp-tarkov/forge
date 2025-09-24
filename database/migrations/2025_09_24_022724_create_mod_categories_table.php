<?php

declare(strict_types=1);

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
        Schema::create('mod_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('hub_id')->nullable()->unique()->index()->comment('Hub category ID');
            $table->foreignId('parent_category_id')->nullable()->constrained('mod_categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('show_order')->default(0);
            $table->timestamps();

            $table->index('parent_category_id');
            $table->index('show_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_categories');
    }
};
