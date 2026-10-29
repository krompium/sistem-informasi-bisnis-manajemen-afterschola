<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 2 (MGT-20): slot trial berkapasitas per cabang. Menggantikan program_trial_schedules (mingguan).
    public function up(): void
    {
        Schema::create('trial_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at'); // instan UTC (app timezone), dikonversi ke WIB di frontend
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_slots');
    }
};
