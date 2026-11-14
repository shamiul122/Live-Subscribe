<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('conversation_id');

            $table->string('provider_message_id', 160)->nullable();
            // Values come from App\Enums\MessageDirection.
            $table->string('direction', 20);
            // Values come from App\Enums\MessageType.
            $table->string('message_type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('sender_type')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();

            // Provider message IDs are deduplicated per organization.
            $table->unique(['organization_id', 'provider_message_id']);

            $table->index(['conversation_id', 'created_at']);
            $table->index(['organization_id', 'direction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
