<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\MembershipLevel;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('membership_level')->default(MembershipLevel::Drifter->value)->after('email_verified_at');
            $table->string('staff_department')->nullable()->after('membership_level');
            $table->boolean('is_officer')->default(false)->after('staff_department');
            $table->string('staff_title')->nullable()->after('is_officer');
        });

        DB::table('users')
            ->whereNull('membership_level')
            ->update(['membership_level' => MembershipLevel::Traveler->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'membership_level',
                'staff_department',
                'is_officer',
                'staff_title',
            ]);
        });
   }
};
