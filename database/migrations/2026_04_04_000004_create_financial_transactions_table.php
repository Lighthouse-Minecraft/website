<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->unsignedBigInteger('amount');
            $table->date('transacted_at');
            $table->foreignId('financial_category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->foreignId('target_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->cascadeOnDelete();
            $table->string('external_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
