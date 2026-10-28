<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            // Log ikut terhapus bersama task-nya.
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // null = pembuatnya sudah dihapus (UserController::destroy menghapus permanen),
            // atau kelak entri otomatis dari webhook GitHub yang tidak punya user.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_updates');
    }
};
