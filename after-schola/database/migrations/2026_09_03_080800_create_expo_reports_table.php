<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Laporan Ekspo/Free-Trial (pengganti Google Form), terpisah dari absensi murid.
    public function up(): void
    {
        Schema::create('expo_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('team_name')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->enum('on_schedule', ['ya', 'sebagian', 'tidak'])->nullable();
            $table->text('enthusiasm')->nullable();
            $table->boolean('has_issue')->default(false);
            $table->text('issue_note')->nullable();
            $table->string('doc_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expo_reports');
    }
};
