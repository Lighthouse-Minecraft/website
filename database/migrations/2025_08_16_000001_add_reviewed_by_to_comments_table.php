<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('comments', 'reviewed_by')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('status');
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('comments', 'reviewed_by')) {
            Schema::table('comments', function (Blueprint $table) {
                // Drop FK first if it exists
                try {
                    $table->dropForeign(['reviewed_by']);
                } catch (\Throwable $e) { /* ignore if missing */
                }
                $table->dropColumn('reviewed_by');
            });
        }
    }
};
