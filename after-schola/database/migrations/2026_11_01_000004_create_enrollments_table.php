<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Keanggotaan siswa di kelas pada periode tertentu (Modul 6b, MGT-32). Satu enrollment aktif per siswa
    // dijaga di aplikasi (StudentPlacement), seperti di PRD §7.
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['aktif', 'selesai', 'pindah'])->default('aktif');
            $table->text('level_override_reason')->nullable(); // BR-10: wajib bila level kelas beda dari rekomendasi trial
            $table->timestamps();

            $table->index(['class_group_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
