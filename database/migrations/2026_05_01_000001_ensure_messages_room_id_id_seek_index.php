<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'messages_room_id_id_seek_index';

    public function up(): void
    {
        if (Schema::hasIndex('messages', ['room_id', 'id'])) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['room_id', 'id'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('messages', self::INDEX_NAME)) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
