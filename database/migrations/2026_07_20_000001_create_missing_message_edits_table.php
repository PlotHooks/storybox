<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('message_edits')) {
            return;
        }

        Schema::create('message_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('editor_user_id');
            $table->text('old_body');
            $table->text('new_body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
    }
};
