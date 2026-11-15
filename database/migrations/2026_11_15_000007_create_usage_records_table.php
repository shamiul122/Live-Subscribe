<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id');

            $table->string('feature', 50);

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            // Fast current usage summary for the billing period.
            $table->unsignedBigInteger('used')->default(0);

            // Historical accuracy even if the plan changes later.
            $table->unsignedBigInteger('limit_snapshot')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'feature', 'period_start']);

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
