<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = task_id baru boleh mulai setelah depends_on_id selesai.
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
