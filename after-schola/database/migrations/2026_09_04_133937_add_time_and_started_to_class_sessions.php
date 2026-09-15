<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Jam pertemuan (opsional) untuk detail jadwal.
            $table->time('start_time')->nullable()->after('date');
            $table->time('end_time')->nullable()->after('start_time');
            // Waktu sesi dimulai trainer/management (buka → mulai sesi → absen).
            $table->timestamp('started_at')->nullable()->after('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'started_at']);
        });
    }
};
