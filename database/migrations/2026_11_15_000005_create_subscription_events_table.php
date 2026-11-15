<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id');

            // Values come from App\Enums\SubscriptionEventType.
            $table->string('type', 40);

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();

            // null actor means the system (scheduler, gateway callback).
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Sanitized context only. Never store secrets.
            $table->json('data')->nullable();

            // Append-only: no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['subscription_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
