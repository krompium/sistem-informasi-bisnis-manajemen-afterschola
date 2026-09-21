<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hot_issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            // info = pengumuman biasa · warning = perlu perhatian · critical = mendesak
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_issues');
    }
};
