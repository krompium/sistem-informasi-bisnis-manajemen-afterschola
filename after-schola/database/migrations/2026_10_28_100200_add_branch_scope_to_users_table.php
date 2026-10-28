<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // all = pusat (lintas cabang); assigned = hanya cabang di branch_user.
            $table->enum('branch_scope', ['all', 'assigned'])->default('assigned')->after('is_active');
        });

        // User yang sudah ada sebelum cabang dikenal tetap tanpa batasan, agar tidak terkunci.
        DB::table('users')->update(['branch_scope' => 'all']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('branch_scope');
        });
    }
};
