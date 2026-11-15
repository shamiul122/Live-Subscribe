<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('connected_account_id');

            $table->string('provider', 40);
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id', 160)->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('connected_account_id')->references('id')->on('connected_accounts')->cascadeOnDelete();

            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['message_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
