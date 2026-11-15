<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('disk', 40)->default('local')->after('storage_path');
            $table->unsignedBigInteger('uploaded_by_id')->nullable()->after('disk');

            $table->foreign('uploaded_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_id']);
            $table->dropColumn(['disk', 'uploaded_by_id']);
        });
    }
};
