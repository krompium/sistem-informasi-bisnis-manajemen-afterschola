<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 2: lead memesan slot trial berkapasitas (trial_slots), bukan jadwal mingguan program.
    // Isi program_trial_schedule_id lama dibuang (data demo, sudah dikonfirmasi).
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['program_trial_schedule_id']);
            $table->dropColumn('program_trial_schedule_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('trial_slot_id')->nullable()->after('program_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trial_slot_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('program_trial_schedule_id')->nullable()->index();
        });
    }
};
