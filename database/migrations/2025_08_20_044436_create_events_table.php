<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_name')->nullable()->index();
            $table->json('event_data')->nullable();
            $table->mediumText('url')->nullable();
            $table->mediumText('referer')->nullable();
            $table->text('languages')->nullable();
            $table->text('useragent')->nullable();
            $table->text('device')->nullable();
            $table->text('platform')->nullable();
            $table->text('browser')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->nullableMorphs('visitable');
            $table->nullableMorphs('visitor');
            $table->char('country_code', 2)->nullable()->index();
            $table->string('country_name', 100)->nullable();
            $table->string('region_name', 100)->nullable();
            $table->string('city_name', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
}
