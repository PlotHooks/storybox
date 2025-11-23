<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add the column if it doesn't already exist
        if (! Schema::hasColumn('rooms', 'created_by')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        // Only drop the column if it exists
        if (Schema::hasColumn('rooms', 'created_by')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
