<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('applicant_age')->nullable()->after('interview_thread_id');
            $table->date('applicant_member_since')->nullable()->after('applicant_age');
            $table->string('applicant_membership_level')->nullable()->after('applicant_member_since');
            $table->date('applicant_membership_level_since')->nullable()->after('applicant_membership_level');
            $table->unsignedInteger('applicant_report_count')->default(0)->after('applicant_membership_level_since');
            $table->unsignedInteger('applicant_commendation_count')->default(0)->after('applicant_report_count');
        });
    }

    public function down(): void
    {
        Schema::table('staff_applications', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_age',
                'applicant_member_since',
                'applicant_membership_level',
                'applicant_membership_level_since',
                'applicant_report_count',
                'applicant_commendation_count',
            ]);
        });
    }
};
