<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id');

            // Only the SHA-256 hash of the raw key is ever persisted.
            $table->char('key_hash', 64)->unique();

            // Non-secret identification helpers for support and display.
            $table->string('key_prefix', 16);
            $table->char('key_last_four', 4);

            $table->string('label', 80)->nullable();

            // Values come from App\Enums\LicenseStatus.
            $table->string('status', 20)->default('active');

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->foreignId('revoked_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index('subscription_id');
            $table->index('key_last_four');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
