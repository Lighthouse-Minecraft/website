<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->unsignedBigInteger('commentable_id');
            $table->string('commentable_type');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->unsignedBigInteger('created_by')->nullable()->constrained('comments')->onDelete('cascade');
            $table->unsignedBigInteger('updated_by')->nullable()->constrained('comments')->onDelete('cascade');
            $table->timestamp('edited_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('updated_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->boolean('needs_review')->default(false)->after('reviewed_at');
            $table->timestamps();

            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
