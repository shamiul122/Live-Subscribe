<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Null for a brand new subscription; set for renewal/plan changes.
            $table->unsignedBigInteger('subscription_id')->nullable();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();

            // Values come from App\Enums\OrderStatus / order types.
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');

            // Server-derived snapshot. Never trusted from the browser.
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            // Globally unique so a repeated checkout request cannot create a
            // second order.
            $table->string('idempotency_key', 191)->unique();

            // null means the system (scheduled renewal).
            $table->foreignId('placed_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Required for the composite tenant foreign key on payment_transactions.
            $table->unique(['id', 'organization_id']);

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index('subscription_id');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
