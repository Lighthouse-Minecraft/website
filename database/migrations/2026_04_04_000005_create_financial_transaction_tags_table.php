<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transaction_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['financial_transaction_id', 'financial_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transaction_tags');
    }
};
