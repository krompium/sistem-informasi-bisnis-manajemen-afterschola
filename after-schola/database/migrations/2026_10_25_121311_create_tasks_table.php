<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // restrict: proyek yang masih punya task tidak bisa dihapus (Q5).
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            // Wajib diisi di API (Q7), tapi nullable di DB: UserController::destroy menghapus user
            // secara permanen, dan task-nya jangan sampai ikut hilang atau memblokir penghapusan.
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'review', 'done'])->default('todo');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('due_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
