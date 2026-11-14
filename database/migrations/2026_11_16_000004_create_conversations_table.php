<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('connected_account_id');
            $table->unsignedBigInteger('contact_id')->nullable();

            $table->string('provider', 40);
            $table->string('provider_conversation_id', 160)->nullable();
            $table->string('subject', 255)->nullable();

            // Values come from App\Enums\ConversationStatus.
            $table->string('status', 20)->default('open');
            // Values come from App\Enums\ConversationPriority.
            $table->string('priority', 20)->default('normal');

            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('connected_account_id')
                ->references('id')
                ->on('connected_accounts')
                ->cascadeOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();

            $table->foreign('assigned_to_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // A provider conversation must not be reused across organizations.
            $table->unique(['organization_id', 'provider', 'provider_conversation_id']);

            $table->index(['organization_id', 'status', 'updated_at']);
            $table->index(['organization_id', 'last_message_at']);
            $table->index(['assigned_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
