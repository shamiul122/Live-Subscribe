<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('invited_by_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('accepted_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Normalized (trimmed, lower-cased) in application code.
            $table->string('email');

            $table->string('role', 20)->default('member');

            // Only the hash of the invitation token is persisted.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'accepted_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
