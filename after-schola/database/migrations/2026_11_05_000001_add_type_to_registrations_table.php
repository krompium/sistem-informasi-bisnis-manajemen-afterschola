<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // PRD §7: baru (lewat trial dan keputusan) atau perpanjangan (BR-18).
            $table->string('type', 20)->default('baru')->after('program_id');
            // Perpanjangan tidak punya keputusan trial. Unique tetap berlaku untuk nilai yang terisi.
            $table->foreignId('decision_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
