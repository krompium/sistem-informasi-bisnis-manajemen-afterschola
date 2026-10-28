<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Bukan unik: invoice yang di-void bisa diterbitkan ulang untuk pendaftaran yang sama.
            $table->foreignId('registration_id')->constrained('registrations')->restrictOnDelete();
            $table->string('number')->unique();
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status', 20)->default('unpaid'); // unpaid | paid | void
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
