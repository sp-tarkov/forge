<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log for the self-serve OAuth client lifecycle. Required by ADR 0001's mitigations for unapproved
     * client registration: when an abuse report comes in we need to identify who created/edited/regenerated a
     * client and from where.
     *
     * `client_id` is nullable and onDelete=set null so deleted clients leave their event history intact (we want
     * "client X was created and then deleted by user Y" to survive the deletion of X).
     */
    public function up(): void
    {
        Schema::create('oauth_client_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('client_id')->nullable()->index();
            // `constrained()` creates the foreign-key constraint; MySQL auto-indexes the underlying column.
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40)->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_events');
    }
};
