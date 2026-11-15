<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Public, immutable identifier. Slugs are reserved permanently,
            // including archived plans.
            $table->string('slug', 80)->unique();

            $table->string('name', 120);
            $table->text('description')->nullable();

            // Money is always an integer in minor units. Never a float.
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3);

            $table->string('billing_interval', 10);
            $table->unsignedSmallInteger('billing_interval_count')->default(1);

            $table->unsignedSmallInteger('trial_days')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'archived_at', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
