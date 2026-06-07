<?php

use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->timestamp('last_posted_at')->nullable()->after('updated_at');
            $table->index(['created_by', 'type', 'deleted_at'], 'rooms_retention_owner_active_idx');
            $table->index(['type', 'deleted_at', 'last_posted_at', 'created_at'], 'rooms_retention_activity_idx');
        });

        $latestMessageTimes = DB::table('messages')
            ->join('rooms', 'rooms.id', '=', 'messages.room_id')
            ->where('rooms.type', Room::TYPE_PUBLIC)
            ->groupBy('messages.room_id')
            ->orderBy('messages.room_id')
            ->get([
                'messages.room_id',
                DB::raw('MAX(messages.created_at) as last_posted_at'),
            ]);

        foreach ($latestMessageTimes as $row) {
            DB::table('rooms')
                ->where('id', $row->room_id)
                ->update(['last_posted_at' => $row->last_posted_at]);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_retention_owner_active_idx');
            $table->dropIndex('rooms_retention_activity_idx');
            $table->dropColumn('last_posted_at');
        });
    }
};
