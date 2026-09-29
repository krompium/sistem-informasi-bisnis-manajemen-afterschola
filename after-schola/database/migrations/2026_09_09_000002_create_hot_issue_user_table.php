<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks which user has dismissed which hot issue, sehingga popup
        // tidak muncul ulang untuk user yang sama setelah ditutup.
        Schema::create('hot_issue_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hot_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->unique(['hot_issue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_issue_user');
    }
};
