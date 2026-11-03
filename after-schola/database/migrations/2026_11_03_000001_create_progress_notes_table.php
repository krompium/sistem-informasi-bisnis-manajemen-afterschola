<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Catatan progres per siswa per sesi (TRN-08). Tanpa unique: satu sesi boleh punya beberapa catatan.
    // restrictOnDelete: catatan adalah riwayat siswa, tidak ikut hilang bila sesi dihapus.
    public function up(): void
    {
        Schema::create('progress_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['student_id', 'class_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_notes');
    }
};
