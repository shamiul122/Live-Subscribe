<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('message_id')->nullable();

            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->string('attachment_type', 40)->default('file');
            $table->string('provider_media_id', 160)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();

            $table->index(['organization_id', 'created_at']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
