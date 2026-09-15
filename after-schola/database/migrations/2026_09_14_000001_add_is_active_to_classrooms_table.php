<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            // Nonaktifkan mata pelajaran untuk sekolah tertentu tanpa menghapus
            // datanya (histori murid/sesi yang sudah ada tetap aman).
            $table->boolean('is_active')->default(true)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
