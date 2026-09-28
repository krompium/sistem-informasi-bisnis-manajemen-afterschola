<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_evaluations', function (Blueprint $table) {
            $table->id();
            // Referensi ke modul lain — sengaja TANPA foreign key dulu karena tabel leads (Modul 2)
            // dan programs (Modul 1) dikerjakan dev lain. Tambahkan FK setelah tabelnya fix.
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('program_id')->nullable()->index();

            // Data calon siswa disimpan langsung agar modul ini bisa berjalan mandiri.
            $table->string('participant_name');
            $table->string('origin_school')->nullable();
            $table->string('origin_class', 50)->nullable();

            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->date('trial_date');
            $table->enum('attendance', ['hadir', 'tidak_hadir'])->default('hadir');

            // Skor per aspek, skala 1–5
            $table->unsignedTinyInteger('score_enthusiasm')->nullable();
            $table->unsignedTinyInteger('score_understanding')->nullable();
            $table->unsignedTinyInteger('score_focus')->nullable();
            $table->unsignedTinyInteger('score_collaboration')->nullable();

            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->string('recommended_level', 30)->nullable(); // beginner / intermediate
            // Rekomendasi trainer → bahan Keputusan (Modul 4)
            $table->enum('recommendation', ['lanjut', 'ragu', 'tidak'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_evaluations');
    }
};
