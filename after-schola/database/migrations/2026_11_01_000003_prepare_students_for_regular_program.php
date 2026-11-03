<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Siswa reguler (Modul 6b) tidak punya sekolah/classroom (milik jalur ekskul lama), tetapi punya orang tua dan cabang.
    // Absensi sekolah tetap jalan: daftar murid sekolah hanya memuat baris ber-school_id.
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->change();
            $table->foreignId('classroom_id')->nullable()->change();
            $table->foreignId('parent_id')->nullable()->after('classroom_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('parent_id')->constrained()->restrictOnDelete();
        });
    }

    // school_id/classroom_id sengaja tidak dikembalikan ke NOT NULL: baris reguler akan membuatnya gagal.
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
