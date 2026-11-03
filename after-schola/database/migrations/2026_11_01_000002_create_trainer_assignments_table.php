<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Penugasan trainer ke kelas (Modul 6a, MGT-33). Dasar isolasi data trainer (PRD §9).
    public function up(): void
    {
        Schema::create('trainer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->restrictOnDelete();
            $table->enum('role', ['utama', 'asisten']);
            $table->timestamps();

            $table->unique(['class_group_id', 'trainer_id']);
            $table->index('trainer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_assignments');
    }
};
