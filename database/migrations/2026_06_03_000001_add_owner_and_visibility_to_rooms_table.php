<?php

use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('owner_character_id')
                ->nullable()
                ->after('created_by')
                ->constrained('characters')
                ->nullOnDelete();

            $table->string('visibility')
                ->default(Room::VISIBILITY_PUBLIC)
                ->after('type');

            $table->index(['type', 'visibility'], 'rooms_type_vis_idx');
            $table->index('owner_character_id', 'rooms_owner_char_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_type_vis_idx');
            $table->dropIndex('rooms_owner_char_idx');
            $table->dropConstrainedForeignId('owner_character_id');
            $table->dropColumn('visibility');
        });
    }
};
