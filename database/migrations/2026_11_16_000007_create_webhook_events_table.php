<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('provider', 40);
            $table->unsignedBigInteger('connected_account_id')->nullable();
            $table->string('provider_event_id', 160)->nullable();
            $table->string('event_type');
            $table->string('processing_status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->char('payload_hash', 64);

            $table->timestamps();

            $table->foreign('connected_account_id')
                ->references('id')
                ->on('connected_accounts')
                ->nullOnDelete();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'processing_status', 'received_at']);
            $table->index('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
