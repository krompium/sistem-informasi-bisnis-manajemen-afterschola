<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MGT-22: catatan staf pada calon/leads (follow-up personal tetap manual, BRD 8.3).
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
