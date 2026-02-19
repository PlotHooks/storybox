<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = Schema::getColumnListing('messages');

        Schema::table('messages', function (Blueprint $table) use ($columns) {
            // Only add soft deletes if they don't already exist
            if (! in_array('deleted_at', $columns, true)) {
                $table->softDeletes()->index();
            }

            // Only add deleted_by if it doesn't already exist
            if (! in_array('deleted_by', $columns, true)) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            if (Schema::hasColumn('messages', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
