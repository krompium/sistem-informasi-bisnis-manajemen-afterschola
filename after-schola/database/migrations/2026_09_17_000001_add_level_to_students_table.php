<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Tingkat kemampuan murid di mata pelajaran ini — terpisah dari
            // mata pelajaran (classroom) itu sendiri.
            $table->enum('level', ['beginner', 'intermediate'])->nullable()->after('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
