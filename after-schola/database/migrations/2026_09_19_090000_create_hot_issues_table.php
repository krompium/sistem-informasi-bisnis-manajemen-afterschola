<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel hot_issues sudah dibuat migration lain lebih dulu (2026_09_09).
        // Migration ini cuma menambahkan kolom yang dipakai halaman Kelola Hot
        // Issue (message, severity, is_active), tanpa mengubah kolom yang sudah
        // ada (content, priority, is_published) supaya popup Hot Issue yang
        // sudah jalan tidak ikut rusak.
        Schema::table('hot_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('hot_issues', 'message')) {
                $table->text('message')->nullable()->after('content');
            }
            if (! Schema::hasColumn('hot_issues', 'severity')) {
                // info = pengumuman biasa · warning = perlu perhatian · critical = mendesak
                $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->after('priority');
            }
            if (! Schema::hasColumn('hot_issues', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_published');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hot_issues', function (Blueprint $table) {
            $table->dropColumn(['message', 'severity', 'is_active']);
        });
    }
};