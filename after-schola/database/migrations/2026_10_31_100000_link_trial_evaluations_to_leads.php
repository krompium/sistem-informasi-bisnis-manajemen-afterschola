<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 3 (TRN-05, D-03, BR-19): evaluasi trial menempel pada peserta slot (lead), ber-cabang, dan
    // level rekomendasi mengacu ke program_levels (Modul 1) menggantikan string beginner/intermediate.
    // Baris lama yang tidak punya lead tetap ada (kolom nullable) dan hanya terlihat scope cabang 'all'.
    // Nilai recommended_level lama tidak bisa dipetakan ke level program, sehingga dibuang.
    public function up(): void
    {
        // lead_id lama tanpa FK: buang rujukan yatim supaya FK bisa dipasang.
        DB::table('trial_evaluations')->whereNotNull('lead_id')
            ->whereNotIn('lead_id', DB::table('leads')->select('id'))
            ->update(['lead_id' => null]);

        Schema::table('trial_evaluations', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('trial_slot_id')->nullable()->after('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('recommended_level_id')->nullable()->constrained('program_levels')->nullOnDelete();
        });

        // Isi cabang dan slot dari lead. Satu lead hanya boleh punya satu evaluasi per slot: bila ada duplikat lama,
        // hanya evaluasi terbaru yang ikut mendapat slot (yang lain tetap ber-cabang).
        $seen = [];
        foreach (DB::table('trial_evaluations')->whereNotNull('lead_id')->orderByDesc('id')->get(['id', 'lead_id']) as $row) {
            $lead = DB::table('leads')->where('id', $row->lead_id)->first(['branch_id', 'trial_slot_id']);
            DB::table('trial_evaluations')->where('id', $row->id)->update([
                'branch_id' => $lead->branch_id,
                'trial_slot_id' => isset($seen[$row->lead_id]) ? null : $lead->trial_slot_id,
            ]);
            $seen[$row->lead_id] = true;
        }

        Schema::table('trial_evaluations', function (Blueprint $table) {
            $table->unique(['trial_slot_id', 'lead_id']);
            $table->dropColumn('recommended_level');
        });
    }

    public function down(): void
    {
        Schema::table('trial_evaluations', function (Blueprint $table) {
            $table->string('recommended_level', 30)->nullable();
            $table->dropUnique(['trial_slot_id', 'lead_id']);
        });

        Schema::table('trial_evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_level_id');
            $table->dropConstrainedForeignId('trial_slot_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropForeign(['lead_id']);
        });
    }
};
