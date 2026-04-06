<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code');
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'net_assets', 'revenue', 'expense']);
            $table->string('subtype')->nullable();
            $table->text('description')->nullable();
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->enum('fund_type', ['unrestricted', 'restricted'])->default('unrestricted');
            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
        });

        Schema::create('financial_restricted_funds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('month_number');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'reconciling', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'month_number']);
        });

        Schema::create('financial_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('financial_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('zinc');
            $table->timestamps();
        });

        Schema::create('financial_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('financial_periods');
            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->enum('entry_type', ['income', 'expense', 'transfer', 'journal', 'closing']);
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('financial_journal_entries')->nullOnDelete();
            $table->string('donor_email')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('financial_vendors')->nullOnDelete();
            $table->foreignId('restricted_fund_id')->nullable()->constrained('financial_restricted_funds')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('financial_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts');
            $table->unsignedInteger('debit')->default(0);
            $table->unsignedInteger('credit')->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_journal_entry_tags', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->constrained('financial_journal_entries')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('financial_tags')->cascadeOnDelete();
            $table->primary(['journal_entry_id', 'tag_id']);
        });

        Schema::create('financial_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts');
            $table->foreignId('period_id')->constrained('financial_periods');
            $table->unsignedInteger('amount')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'period_id']);
        });

        Schema::create('financial_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts');
            $table->foreignId('period_id')->constrained('financial_periods');
            $table->date('statement_date')->nullable();
            $table->integer('statement_ending_balance')->default(0);
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'period_id']);
        });

        Schema::create('financial_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('financial_reconciliations')->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained('financial_journal_entry_lines')->cascadeOnDelete();
            $table->timestamp('cleared_at');
            $table->timestamps();

            $table->unique(['reconciliation_id', 'journal_entry_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliation_lines');
        Schema::dropIfExists('financial_reconciliations');
        Schema::dropIfExists('financial_budgets');
        Schema::dropIfExists('financial_journal_entry_tags');
        Schema::dropIfExists('financial_journal_entry_lines');
        Schema::dropIfExists('financial_journal_entries');
        Schema::dropIfExists('financial_tags');
        Schema::dropIfExists('financial_vendors');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('financial_restricted_funds');
        Schema::dropIfExists('financial_accounts');
    }
};
