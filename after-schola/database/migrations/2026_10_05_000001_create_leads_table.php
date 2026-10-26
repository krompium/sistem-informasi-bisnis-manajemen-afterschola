<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 2 — Pendaftaran Trial & Leads. Terpisah dari `students` (Absensi),
    // sesuai keputusan PM: satu bisnis, dua tabel. Dikonversi ke `students`
    // oleh Modul 4 (Keputusan & Pendaftaran Resmi) saat orang tua lanjut.
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('education_level', ['sd', 'smp', 'sma']);
            $table->string('origin_school');
            $table->string('parent_email');

            // Referensi ke modul lain — sengaja TANPA foreign key dulu, ikut pola
            // yang sudah dipakai trial_evaluations & registrations (Modul 3/4),
            // karena tabel programs (Modul 1) bisa berubah struktur sebelum fix.
            $table->unsignedBigInteger('program_id')->nullable()->index();
            $table->unsignedBigInteger('program_trial_schedule_id')->nullable()->index();

            $table->enum('status', ['leads', 'trial_scheduled', 'not_continued'])->default('leads');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
