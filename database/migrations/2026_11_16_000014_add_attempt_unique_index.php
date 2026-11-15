<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            // Prevents a duplicated job from recording the same attempt twice.
            $table->unique(['message_id', 'attempt_number'], 'delivery_attempts_message_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_attempts', function (Blueprint $table) {
            $table->dropUnique('delivery_attempts_message_attempt_unique');
        });
    }
};
