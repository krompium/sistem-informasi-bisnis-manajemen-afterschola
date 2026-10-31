<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Jadwal trial mingguan per program digantikan trial_slots (Modul 2). Isinya data demo, tidak dimigrasi.
    public function up(): void
    {
        Schema::dropIfExists('program_trial_schedules');
    }

    public function down(): void
    {
        Schema::create('program_trial_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->timestamps();
        });
    }
};
