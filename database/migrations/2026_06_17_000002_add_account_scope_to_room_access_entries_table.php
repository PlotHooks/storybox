<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_access_entries')) {
            return;
        }

        Schema::table('room_access_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('room_access_entries', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('character_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('room_access_entries', 'scope')) {
                $table->string('scope')
                    ->default('character')
                    ->after('type');
            }
        });

        DB::table('room_access_entries')
            ->whereNull('scope')
            ->update(['scope' => 'character']);

        Schema::table('room_access_entries', function (Blueprint $table) {
            $table->foreignId('character_id')
                ->nullable()
                ->change();
        });

        Schema::table('room_access_entries', function (Blueprint $table) {
            $table->unique(['room_id', 'user_id', 'type', 'scope'], 'room_access_account_scope_uq');
            $table->index(['user_id', 'type', 'scope'], 'room_access_user_scope_idx');
            $table->index(['room_id', 'type', 'scope'], 'room_access_room_scope_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_access_entries')) {
            return;
        }

        Schema::table('room_access_entries', function (Blueprint $table) {
            if (Schema::hasColumn('room_access_entries', 'user_id')) {
                $table->dropUnique('room_access_account_scope_uq');
                $table->dropIndex('room_access_user_scope_idx');
                $table->dropIndex('room_access_room_scope_idx');
                $table->dropConstrainedForeignId('user_id');
            }

            if (Schema::hasColumn('room_access_entries', 'scope')) {
                $table->dropColumn('scope');
            }
        });

        Schema::table('room_access_entries', function (Blueprint $table) {
            $table->foreignId('character_id')
                ->nullable(false)
                ->change();
        });
    }
};
