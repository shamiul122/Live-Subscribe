<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');

            $table->string('provider', 40);
            $table->string('provider_account_id');
            $table->string('account_name');
            $table->string('account_username', 120)->nullable();
            $table->string('account_type', 80)->nullable();

            // Values come from App\Enums\ConnectedAccountStatus.
            $table->string('status', 20)->default('pending');

            // Encrypted at rest via the model casts.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('connected_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            // A provider account must not be connected twice to the same
            // organization.
            $table->unique(['organization_id', 'provider', 'provider_account_id']);

            $table->index(['organization_id', 'status']);
            $table->index('provider');
            $table->index('provider_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_accounts');
    }
};
