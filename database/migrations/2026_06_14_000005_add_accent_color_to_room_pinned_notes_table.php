<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_pinned_notes', function (Blueprint $table) {
            $table->string('accent_color', 20)->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('room_pinned_notes', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
