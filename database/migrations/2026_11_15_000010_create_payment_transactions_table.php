<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('order_id');

            $table->string('provider', 30);
            $table->string('provider_reference', 191)->nullable();

            // Values come from App\Enums\PaymentStatus.
            $table->string('status', 20)->default('pending');

            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message', 255)->nullable();

            // Sanitized provider payload only. Never card data or credentials.
            $table->json('payload')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Multiple null references are allowed; a successful provider
            // reference can only be recorded once.
            $table->unique(['provider', 'provider_reference']);

            $table->foreign(['order_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('orders')
                ->cascadeOnDelete();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
