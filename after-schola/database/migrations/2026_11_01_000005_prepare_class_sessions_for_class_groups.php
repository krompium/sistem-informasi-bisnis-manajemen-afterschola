<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sesi kelas reguler (Modul 6c) memakai tabel yang sama dengan pertemuan sekolah: terikat ke class_group,
    // bukan school/classroom. Baris lama tetap utuh; indeks (trainer_id, date) untuk cek bentrok BR-12.
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->change();
            $table->foreignId('classroom_id')->nullable()->change();
            $table->foreignId('class_group_id')->nullable()->after('classroom_id')->constrained()->restrictOnDelete();
            $table->index(['trainer_id', 'date']);
        });
    }

    // school_id/classroom_id sengaja tidak dikembalikan ke NOT NULL: sesi reguler akan membuatnya gagal.
    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex(['trainer_id', 'date']);
            $table->dropConstrainedForeignId('class_group_id');
        });
    }
};
