<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('text_color_1', 7)->default('#D8F3FF'); // base color
            $table->string('text_color_2', 7)->nullable();
            $table->string('text_color_3', 7)->nullable();
            $table->string('text_color_4', 7)->nullable();

            $table->boolean('fade_message')->default(false);
            $table->boolean('fade_name')->default(false);

            // future (optional): name font/effects
            $table->string('name_font', 32)->nullable();
            $table->string('name_effect', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'text_color_1','text_color_2','text_color_3','text_color_4',
                'fade_message','fade_name',
                'name_font','name_effect',
            ]);
        });
    }
};
