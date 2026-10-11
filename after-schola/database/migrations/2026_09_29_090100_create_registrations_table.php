<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->unique()->constrained('enrollment_decisions')->cascadeOnDelete();
            // Referensi modul lain — tanpa FK (tabel leads/programs dikerjakan dev lain).
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('program_id')->nullable()->index();

            // Data siswa
            $table->string('student_name');
            $table->date('birth_date')->nullable();
            $table->string('origin_school')->nullable();
            $table->string('origin_class', 50)->nullable();
            $table->string('level', 30); // beginner / intermediate

            // Data orang tua / wali
            $table->string('parent_name');
            $table->string('parent_phone', 30);
            $table->string('parent_email')->nullable();
            $table->text('parent_address')->nullable();

            // draft → menunggu_verifikasi (titik serah ke Modul 5: Verifikasi & Pembayaran)
            $table->enum('status', ['draft', 'menunggu_verifikasi'])->default('draft');
            // Diisi nanti saat verifikasi (butuh sekolah & level/classroom yang sudah ada).
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
