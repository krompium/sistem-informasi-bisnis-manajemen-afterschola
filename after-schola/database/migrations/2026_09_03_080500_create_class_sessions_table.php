<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dinamai class_sessions (bukan sessions) agar tak bentrok dengan tabel session driver Laravel.
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete(); // level
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('mode', ['onsite', 'online'])->default('onsite');
            $table->date('date');
            $table->unsignedSmallInteger('meeting_no'); // pertemuan ke-berapa (1..n)
            $table->boolean('is_locked')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'classroom_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
