<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_category_id')->constrained('rule_categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->foreignId('supersedes_rule_id')->nullable()->constrained('rules')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rule_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'submitted', 'published'])->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('version_number');
        });

        Schema::create('rule_version_rules', function (Blueprint $table) {
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->primary(['rule_version_id', 'rule_id']);
        });

        Schema::create('user_rule_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->timestamp('agreed_at');
            $table->timestamps();

            $table->unique(['user_id', 'rule_version_id']);
        });

        Schema::create('discipline_report_rules', function (Blueprint $table) {
            $table->foreignId('discipline_report_id')->constrained('discipline_reports')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->primary(['discipline_report_id', 'rule_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('rules_reminder_sent_at')->nullable()->after('rules_accepted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rules_reminder_sent_at');
        });

        Schema::dropIfExists('discipline_report_rules');
        Schema::dropIfExists('user_rule_agreements');
        Schema::dropIfExists('rule_version_rules');
        Schema::dropIfExists('rule_versions');
        Schema::dropIfExists('rules');
        Schema::dropIfExists('rule_categories');
    }
};
