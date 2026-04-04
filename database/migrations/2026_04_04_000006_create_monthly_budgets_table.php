<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_category_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('planned_amount');
            $table->timestamps();

            $table->unique(['financial_category_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budgets');
    }
};
