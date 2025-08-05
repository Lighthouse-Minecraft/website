<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\MembershipLevel;
use App\Enums\StaffRank;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('membership_level')->default(MembershipLevel::Drifter)->after('email_verified_at');
            $table->unsignedTinyInteger('staff_rank')->default(StaffRank::None)->after('membership_level');
            $table->string('staff_department')->nullable()->after('staff_rank');
            $table->string('staff_title')->nullable()->after('staff_department');
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
                'staff_rank',
                'staff_title',
            ]);
        });
   }
};
