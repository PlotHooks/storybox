<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('type', 20)
                ->default(Message::TYPE_NORMAL)
                ->after('character_id');
        });

        DB::table('messages')
            ->whereNull('type')
            ->update(['type' => Message::TYPE_NORMAL]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
