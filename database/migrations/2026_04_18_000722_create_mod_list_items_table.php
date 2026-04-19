<?php

declare(strict_types=1);

use App\Models\ModList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(ModList::class)
                ->constrained('mod_lists')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('listable_type');
            $table->unsignedBigInteger('listable_id');
            $table->string('note', 280)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('added_as_dependency')->default(false);
            $table->timestamps();

            $table->unique(['mod_list_id', 'listable_type', 'listable_id'], 'mod_list_items_listable_unique');
            $table->index(['listable_type', 'listable_id'], 'mod_list_items_listable_index');
            $table->index(['mod_list_id', 'position'], 'mod_list_items_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_list_items');
    }
};
