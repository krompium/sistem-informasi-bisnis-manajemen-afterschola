<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modul 2 (D-03, BR-19, NFR-04): lead ber-cabang, terhubung ke akun Orangtua, dan mencatat waktu persetujuan data.
    // Nullable karena baris lama (demo) tidak punya nilai; form publik selalu mengisinya.
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->after('branch_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('consented_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('consented_at');
        });
    }
};
