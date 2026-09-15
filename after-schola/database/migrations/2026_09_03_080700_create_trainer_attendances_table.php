<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->enum('status', ['hadir', 'terlambat', 'tidak_hadir'])->default('hadir');
            $table->string('photo_path')->nullable(); // foto (onsite) / screenshot (online)
            $table->decimal('latitude', 10, 7)->nullable();  // null utk online
            $table->decimal('longitude', 10, 7)->nullable(); // null utk online
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'trainer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_attendances');
    }
};
