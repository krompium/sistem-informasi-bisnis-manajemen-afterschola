<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Daftar nama mata pelajaran yang bisa diajar trainer ini (bebas teks,
            // sama seperti nama mata pelajaran di tabel classrooms), dan daftar
            // level yang bisa diajar (beginner/intermediate). Keduanya independen,
            // tidak dipasangkan satu-satu.
            $table->json('subjects_taught')->nullable()->after('is_active');
            $table->json('levels_taught')->nullable()->after('subjects_taught');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subjects_taught', 'levels_taught']);
        });
    }
};
