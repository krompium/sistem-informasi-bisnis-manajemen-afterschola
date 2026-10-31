<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 4 (D-03, BR-19): pendaftaran ber-cabang supaya antrian verifikasi bisa diisolasi.
    // level jadi nullable: kini snapshot nama level program dari evaluasi trial (bisa kosong bila program belum punya level).
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('lead_id')->constrained()->restrictOnDelete();
            $table->string('level', 30)->nullable()->change();
        });

        // Cabang diturunkan dari evaluasi trial pendaftaran ini (baris lama tanpa cabang tetap null: hanya scope 'all').
        DB::statement('update registrations set branch_id = (
            select te.branch_id from enrollment_decisions d
            join trial_evaluations te on te.id = d.evaluation_id
            where d.id = registrations.decision_id
        )');
    }

    public function down(): void
    {
        // level tetap nullable: mengembalikan NOT NULL bisa gagal bila sudah ada baris tanpa level.
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
