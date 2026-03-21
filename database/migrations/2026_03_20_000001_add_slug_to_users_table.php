<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Backfill slugs for all existing users
        $users = User::all();
        foreach ($users as $user) {
            $baseSlug = Str::slug($user->name);
            if ($baseSlug === '') {
                $baseSlug = 'user';
            }

            $slug = $baseSlug;
            $suffix = 2;

            while (User::where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $user->slug = $slug;
            $user->save();
        }

        // Now make the column non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
