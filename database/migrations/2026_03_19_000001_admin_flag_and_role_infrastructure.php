<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add admin_granted_at to users table
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('admin_granted_at')->nullable()->after('promoted_at');
        });

        // 2. Migrate existing Admin role users to admin_granted_at
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        if ($adminRoleId) {
            $adminUserIds = DB::table('role_user')
                ->where('role_id', $adminRoleId)
                ->pluck('user_id');

            if ($adminUserIds->isNotEmpty()) {
                DB::table('users')
                    ->whereIn('id', $adminUserIds)
                    ->update(['admin_granted_at' => now()]);
            }
        }

        // 3. Add has_all_roles_at to staff_positions table
        Schema::table('staff_positions', function (Blueprint $table) {
            $table->timestamp('has_all_roles_at')->nullable()->after('accepting_applications');
        });

        // 4. Create role_staff_position pivot table
        Schema::create('role_staff_position', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_position_id')->constrained()->onDelete('cascade');
            $table->unique(['role_id', 'staff_position_id']);
            $table->timestamps();
        });

        // 5. Delete redundant role records
        DB::table('roles')
            ->whereIn('name', ['Stowaway', 'Traveler', 'Resident', 'Citizen', 'Guest', 'Admin'])
            ->delete();

        // 6. Drop the role_user pivot table
        Schema::dropIfExists('role_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate role_user pivot table
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Drop role_staff_position pivot table
        Schema::dropIfExists('role_staff_position');

        // Remove has_all_roles_at from staff_positions
        Schema::table('staff_positions', function (Blueprint $table) {
            $table->dropColumn('has_all_roles_at');
        });

        // Remove admin_granted_at from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_granted_at');
        });
    }
};
