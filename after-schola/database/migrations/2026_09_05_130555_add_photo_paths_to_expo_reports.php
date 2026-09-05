<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expo_reports', function (Blueprint $table) {
            // Foto dokumentasi kegiatan (bisa lebih dari satu), disimpan sbg array path.
            $table->json('photo_paths')->nullable()->after('doc_url');
        });
    }

    public function down(): void
    {
        Schema::table('expo_reports', function (Blueprint $table) {
            $table->dropColumn('photo_paths');
        });
    }
};
