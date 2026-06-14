<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('status')->index();
        });

        $entries = DB::table('world_book_entries')
            ->select(['id', 'category', 'draft_category', 'title', 'draft_title'])
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(category, draft_category) asc')
            ->orderByRaw('COALESCE(title, draft_title) asc')
            ->get();

        $positions = [];

        foreach ($entries as $entry) {
            $category = $entry->category ?? $entry->draft_category;

            if (! is_string($category) || $category === '') {
                continue;
            }

            $positions[$category] = ($positions[$category] ?? 0) + 1;

            DB::table('world_book_entries')
                ->where('id', $entry->id)
                ->update(['sort_order' => $positions[$category]]);
        }
    }

    public function down(): void
    {
        Schema::table('world_book_entries', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
