<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('profile_banner_url', 2048)->nullable()->after('visibility');
            $table->text('profile_summary')->nullable()->after('profile_banner_url');
            $table->text('profile_joining_information')->nullable()->after('profile_summary');
            $table->text('profile_rules')->nullable()->after('profile_joining_information');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'profile_banner_url',
                'profile_summary',
                'profile_joining_information',
                'profile_rules',
            ]);
        });
    }
};
