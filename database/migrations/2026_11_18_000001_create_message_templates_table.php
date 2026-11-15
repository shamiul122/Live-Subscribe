<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('created_by_id')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            // Provider/channel key, e.g. App\Enums\SocialProvider value.
            $table->string('channel', 40)->nullable();
            // Values come from App\Enums\MessageTemplateCategory.
            $table->string('category', 40)->default('custom');
            // Values come from App\Enums\MessageTemplateStatus.
            $table->string('status', 20)->default('draft');

            // Subject is only meaningful for email templates.
            $table->string('subject')->nullable();
            $table->text('content');

            // Detected placeholder keys, e.g. ["customer_name"]. Derived from
            // the content on save so it can never drift from the body.
            $table->json('variables')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'channel']);
            $table->index(['organization_id', 'category']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
