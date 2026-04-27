<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false);
            }

            if (! Schema::hasColumn('users', 'banned_until')) {
                $table->timestamp('banned_until')->nullable();
            }

            if (! Schema::hasColumn('users', 'banned_reason')) {
                $table->text('banned_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_banned')) {
                $table->dropColumn('is_banned');
            }

            if (Schema::hasColumn('users', 'banned_until')) {
                $table->dropColumn('banned_until');
            }

            if (Schema::hasColumn('users', 'banned_reason')) {
                $table->dropColumn('banned_reason');
            }
        });
    }
};