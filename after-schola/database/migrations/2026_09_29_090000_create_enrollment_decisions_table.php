<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_decisions', function (Blueprint $table) {
            $table->id();
            // Satu keputusan per evaluasi trial (bisa diubah, tidak menumpuk).
            $table->foreignId('evaluation_id')->unique()->constrained('trial_evaluations')->cascadeOnDelete();
            $table->enum('decision', ['lanjut', 'tidak', 'pikir_pikir']);
            $table->text('reason')->nullable();          // alasan, terutama bila tidak lanjut
            $table->date('decided_at');
            $table->date('follow_up_date')->nullable();  // untuk "pikir-pikir"
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_decisions');
    }
};
