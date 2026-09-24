<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // mis. "September 2026", diisi bebas oleh trainer
            $table->decimal('score', 5, 2);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};