<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Evaluasi periodik satu siswa (enrollment) pada satu periode (TRN-09). period = label bebas, mis. 2026-11.
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('period', 30);
            $table->text('summary')->nullable();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
