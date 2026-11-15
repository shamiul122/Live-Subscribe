<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            // Sanitized provider payload kept so the queued job can normalize it
            // without re-reading the HTTP request.
            $table->json('payload')->nullable()->after('payload_hash');
            $table->unique(['provider', 'payload_hash'], 'webhook_events_provider_payload_unique');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('webhook_events_provider_payload_unique');
            $table->dropColumn('payload');
        });
    }
};
