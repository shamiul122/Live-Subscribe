<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('contact_id')->nullable();

            // Values come from App\Enums\CampaignRecipientStatus.
            $table->string('status', 20)->default('pending');
            // Delivery placeholder that mirrors App\Enums\MessageDeliveryStatus
            // once the communication engine integration lands.
            $table->string('delivery_status', 20)->nullable();
            $table->string('error_message')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Provider message reference placeholder for Step 6 integration.
            $table->string('provider_message_id', 160)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->cascadeOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();

            // A contact can only be a recipient of a campaign once.
            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
