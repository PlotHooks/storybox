<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = Schema::getColumnListing('message_reports');

        Schema::table('message_reports', function (Blueprint $table) use ($columns) {
            if (! in_array('message_id', $columns, true)) {
                $table->foreignId('message_id')
                    ->after('id')
                    ->constrained('messages')
                    ->cascadeOnDelete();
            }

            if (! in_array('reporter_user_id', $columns, true)) {
                $table->foreignId('reporter_user_id')
                    ->after('message_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! in_array('reason', $columns, true)) {
                $table->text('reason')->after('reporter_user_id');
            }

            if (! in_array('notes', $columns, true)) {
                $table->text('notes')->nullable()->after('reason');
            }

            if (! in_array('status', $columns, true)) {
                $table->string('status')->default('pending')->after('notes');
            }

            if (! in_array('reviewed_by', $columns, true)) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! in_array('reviewed_at', $columns, true)) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_reports', function (Blueprint $table) {
            if (Schema::hasColumn('message_reports', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            if (Schema::hasColumn('message_reports', 'message_id')) {
                $table->dropConstrainedForeignId('message_id');
            }

            if (Schema::hasColumn('message_reports', 'reporter_user_id')) {
                $table->dropConstrainedForeignId('reporter_user_id');
            }

            foreach (['reviewed_at', 'status', 'notes', 'reason'] as $column) {
                if (Schema::hasColumn('message_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
