<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Values come from App\Enums\MessageDeliveryStatus.
            $table->string('delivery_status', 20)->nullable()->after('message_type');
            // Client supplied key used to make outbound queueing idempotent.
            $table->string('idempotency_key', 80)->nullable()->after('delivery_status');

            $table->unique(['organization_id', 'idempotency_key'], 'messages_organization_idempotency_unique');
            $table->index(['organization_id', 'delivery_status'], 'messages_organization_delivery_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_organization_idempotency_unique');
            $table->dropIndex('messages_organization_delivery_status_index');
            $table->dropColumn(['delivery_status', 'idempotency_key']);
        });
    }
};
