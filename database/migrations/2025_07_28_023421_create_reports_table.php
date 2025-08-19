<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->enum('reason', array_column(ReportReason::cases(), 'value'));
            $table->text('context')->nullable();
            $table->enum('status', array_column(ReportStatus::cases(), 'value'))->default(ReportStatus::PENDING->value);
            $table->timestamps();

            $table->unique(['reporter_id', 'reportable_id', 'reportable_type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
