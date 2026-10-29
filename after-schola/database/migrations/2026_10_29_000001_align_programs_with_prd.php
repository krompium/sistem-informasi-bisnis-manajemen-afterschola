<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRD §7 + konvensi §2: identifier Inggris dan jenjang. Kolom `tipe` (online/offline) dipertahankan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('deskripsi', 'description');
            $table->renameColumn('biaya', 'price');
            $table->renameColumn('status_aktif', 'is_active');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->change();
            // Nullable karena baris lama belum punya jenjang; wajib diisi lewat Form Request.
            $table->enum('jenjang', ['SD', 'SMP', 'SMA'])->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('jenjang');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('name', 'nama');
            $table->renameColumn('description', 'deskripsi');
            $table->renameColumn('price', 'biaya');
            $table->renameColumn('is_active', 'status_aktif');
        });
    }
};
