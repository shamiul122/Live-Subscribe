<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_connection_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('connected_account_id')->nullable();
            $table->string('type');
            $table->string('status', 20)->nullable();

            // Metadata only; tokens and secrets must never be stored here.
            $table->json('data')->nullable();

            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('connected_account_id')
                ->references('id')
                ->on('connected_accounts')
                ->nullOnDelete();

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'created_at']);
            $table->index('connected_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_connection_events');
    }
};
