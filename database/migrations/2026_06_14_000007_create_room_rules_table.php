<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['room_id', 'sort_order']);
        });

        DB::table('rooms')
            ->select(['id', 'profile_rules'])
            ->whereNotNull('profile_rules')
            ->orderBy('id')
            ->chunkById(100, function ($rooms) {
                $now = now();
                $rows = [];

                foreach ($rooms as $room) {
                    $rules = trim((string) $room->profile_rules);

                    if ($rules === '') {
                        continue;
                    }

                    $rows[] = [
                        'room_id' => $room->id,
                        'title' => 'Room Rules',
                        'body' => $rules,
                        'sort_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('room_rules')->insert($rows);
                    DB::table('rooms')
                        ->whereIn('id', array_column($rows, 'room_id'))
                        ->update(['profile_rules' => null]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rules');
    }
};
