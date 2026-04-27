<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_reports', function (Blueprint $table) {
            $table->unique(
                ['message_id', 'reporter_user_id'],
                'message_reports_unique_reporter_message'
            );
        });
    }

    public function down(): void
    {
        Schema::table('message_reports', function (Blueprint $table) {
            $table->dropUnique('message_reports_unique_reporter_message');
        });
    }
};
