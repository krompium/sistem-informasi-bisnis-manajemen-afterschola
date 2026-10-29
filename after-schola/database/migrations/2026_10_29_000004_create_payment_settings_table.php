<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Satu baris global (D: pengaturan pembayaran tidak per cabang). Lihat PaymentSetting::current().
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('due_days')->default(7);
            $table->unsignedSmallInteger('reminder_interval_days')->default(3);
            $table->text('payment_instructions')->nullable();
            $table->text('bank_account')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
