<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Skor per kriteria rubrik (bukan JSON, PRD §7). restrictOnDelete: kriteria yang sudah dinilai tidak bisa dihapus.
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('rubric_criteria')->restrictOnDelete();
            $table->unsignedSmallInteger('score');
            $table->timestamps();

            $table->unique(['evaluation_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
