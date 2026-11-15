<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            // Values come from App\Enums\Feature.
            $table->string('feature', 50);

            // Values come from App\Enums\FeatureType.
            $table->string('feature_type', 20);

            // null = unlimited, 0 = included but unavailable, positive = quota.
            $table->unsignedBigInteger('limit')->nullable();

            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
