<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_audience_snapshots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('captured_by_id')->nullable();

            // Values come from App\Enums\CampaignAudienceType.
            $table->string('audience_type', 20)->default('all');
            // The audience configuration as it was when the snapshot was taken.
            $table->json('filters')->nullable();
            // Frozen list of contact ids so later contact edits do not change
            // the campaign's intended audience.
            $table->json('contact_ids')->nullable();
            $table->unsignedInteger('contact_count')->default(0);
            $table->timestamp('captured_at')->nullable();
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

            $table->foreign('captured_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // A campaign keeps a single active audience snapshot.
            $table->unique(['campaign_id']);
            $table->index(['organization_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_audience_snapshots');
    }
};
