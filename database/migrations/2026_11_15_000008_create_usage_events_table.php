<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id');

            $table->string('feature', 50);
            $table->timestamp('period_start');

            // Durable idempotency ledger: the unique index below guarantees a
            // repeated request can never consume usage twice.
            $table->string('idempotency_key', 191);

            $table->unsignedBigInteger('units')->default(1);

            $table->timestamp('created_at')->nullable();

            $table->unique(['subscription_id', 'feature', 'idempotency_key']);

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
