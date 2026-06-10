<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('body');
            $table->json('draft_tags')->nullable()->after('draft_body');
            $table->text('rejection_note')->nullable()->after('reviewed_at');
            $table->timestamp('rejected_at')->nullable()->after('rejection_note');
        });
    }

    public function down(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->dropColumn(['tags', 'draft_tags', 'rejection_note', 'rejected_at']);
        });
    }
};
