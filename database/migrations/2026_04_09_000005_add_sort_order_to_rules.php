<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('created_by_user_id');
        });

        // Seed initial sort_order for existing rules based on their id within each category
        $categories = DB::table('rule_categories')->orderBy('id')->pluck('id');
        foreach ($categories as $categoryId) {
            $rules = DB::table('rules')->where('rule_category_id', $categoryId)->orderBy('id')->get();
            foreach ($rules as $index => $rule) {
                DB::table('rules')->where('id', $rule->id)->update(['sort_order' => ($index + 1) * 10]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
