<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('connected_account_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            // Values come from App\Enums\CampaignType.
            $table->string('type', 40)->default('custom');
            // Provider/channel key, e.g. App\Enums\SocialProvider value.
            $table->string('channel', 40)->nullable();
            // Values come from App\Enums\CampaignStatus.
            $table->string('status', 20)->default('draft');

            $table->text('message_content')->nullable();

            // Values come from App\Enums\CampaignAudienceType.
            $table->string('audience_type', 20)->default('all');
            $table->json('audience_config')->nullable();
            // Denormalised count from the latest audience snapshot.
            $table->unsignedInteger('audience_size')->default(0);

            // Scheduling placeholder for Step 10. Nothing is delivered here.
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

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
                ->nullOnDelete();

            $table->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'channel']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
