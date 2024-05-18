<?php

use App\Models\License;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mods', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained('users');
            $table->string('name');
            $table->string('slug');
            $table->longText('description');
            $table->foreignIdFor(License::class)->constrained('licenses');
            $table->string('source_code_link');
            $table->boolean('suggested')->default(false);
            $table->boolean('contains_ai_content')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mods');
    }
};
