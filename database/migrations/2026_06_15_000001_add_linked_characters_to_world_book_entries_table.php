<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->foreignId('linked_character_id')
                ->nullable()
                ->after('reviewed_by_character_id')
                ->constrained('characters')
                ->nullOnDelete();

            $table->foreignId('draft_linked_character_id')
                ->nullable()
                ->after('linked_character_id')
                ->constrained('characters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_linked_character_id');
            $table->dropConstrainedForeignId('linked_character_id');
        });
    }
};
