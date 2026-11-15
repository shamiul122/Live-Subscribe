<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_entitlements', function (Blueprint $table) {
            $table->id();

            // organization_id is redundant with subscriptions.organization_id
            // on purpose: it lets the composite foreign key prove tenant
            // consistency at the database level.
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id');

            $table->string('feature', 50);

            // Snapshot of the plan feature limit for this billing period.
            $table->unsignedBigInteger('limit')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'feature']);

            $table->foreign(['subscription_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_entitlements');
    }
};
