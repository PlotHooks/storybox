<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('profile_mode', 32)->default('standard')->after('profile_rules');
            $table->longText('profile_custom_html')->nullable()->after('profile_mode');
            $table->longText('profile_custom_css')->nullable()->after('profile_custom_html');
            $table->longText('profile_custom_js')->nullable()->after('profile_custom_css');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'profile_mode',
                'profile_custom_html',
                'profile_custom_css',
                'profile_custom_js',
            ]);
        });
    }
};
