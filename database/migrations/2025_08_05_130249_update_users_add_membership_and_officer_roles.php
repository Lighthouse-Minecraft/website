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
            $table->unsignedTinyInteger('membership_level')->default(MembershipLevel::Drifter)->after('email_verified_at');
            $table->string('staff_department')->nullable()->after('membership_level');
            $table->unsignedTinyInteger('staff_rank')->nullable()->after('staff_department');
            $table->string('staff_title')->nullable()->after('staff_rank');
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
